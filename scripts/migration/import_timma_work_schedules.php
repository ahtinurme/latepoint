#!/usr/bin/env php
<?php
/**
 * Import agent work schedules from Timma into LatePoint work_periods.
 *
 * SOURCE: Timma's real per-date availability ("Töögraafik" / worktimes), endpoint
 * `/api/worktimes/customer/{biz}?start=YYYY-MM-DD&end=YYYY-MM-DD`. Each agent has a
 * `days[]` array of actual shifts: {dayStart, dayEnd} in UTC. A date present = a
 * working day with that window; a date absent = day off. (The per-user `workTimes`
 * weekly template is NOT used — it is a stale 9-20 default that does not reflect
 * reality.)
 *
 * Written to LatePoint as, per matched agent (agent_meta `timma_user_id`):
 *   - 7 weekly baseline rows (week_day 1..7, custom_date NULL, 0/0 = day off). These
 *     have weight 1 (agent set) so they beat the global 9-20 default (weight 0) — i.e.
 *     the agent is OFF by default, no global-default leak.
 *   - One custom_date row per real working day (week_day = that date's weekday,
 *     start/end = local Europe/Tallinn minutes). custom_date rows have weight 4 so
 *     they win for that specific date. Split shifts (multiple windows/day) supported.
 *
 * Times are converted UTC -> Europe/Tallinn (DST-correct per date). Rows tagged
 * chain_id='timma_import'; a re-run deletes & rebuilds them (idempotent). Also
 * ensures agent<->service<->location connections from imported bookings.
 *
 * Usage: php -d memory_limit=1024M import_timma_work_schedules.php [--token=...] \
 *          [--from=2026-02-01] [--to=YYYY-MM-DD] [--email=] [--biz=] [--dry-run]
 *        (token falls back to .context/timma/timma_auth.json if not passed)
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['token:', 'email:', 'biz:', 'from:', 'to:', 'dry-run']);
$email = $opts['email'] ?? 'carmen.kaljula@gmail.com';
$biz   = $opts['biz']   ?? '673e4475236e6416d9c1fa32';
$from  = $opts['from']  ?? '2026-02-01';
$to    = $opts['to']    ?? (new DateTime('now'))->modify('+90 days')->format('Y-m-d');
$dryRun = isset($opts['dry-run']);

$token = $opts['token'] ?? getenv('TIMMA_TOKEN') ?: '';
if ($token === '') {
    $authFile = __DIR__ . '/../../.context/timma/timma_auth.json';
    if (is_file($authFile)) {
        $auth = json_decode((string) file_get_contents($authFile), true);
        $token = $auth['x-auth-token'] ?? '';
        $email = $auth['x-auth-email'] ?? $email;
    }
}
if ($token === '') { fwrite(STDERR, "ERROR: --token required (or cache .context/timma/timma_auth.json)\n"); exit(1); }

$wpLoad = realpath(__DIR__ . '/../../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsAgentModel')) { fwrite(STDERR, "LatePoint not loaded\n"); exit(1); }
global $wpdb; $P = $wpdb->prefix;

echo "=========== Import agent work schedules (real worktimes) ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | range {$from} .. {$to}\n";
echo "====================================================================\n";

function timma_get(string $path, string $token, string $email) {
    $res = wp_remote_get('https://pro.timma.ee' . $path, ['headers' => ['x-auth-token' => $token, 'x-auth-email' => $email, 'accept' => 'application/json'], 'timeout' => 60]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) { fwrite(STDERR, "Timma {$path} failed (token expired?)\n"); return null; }
    return json_decode(wp_remote_retrieve_body($res), true);
}

$tz = new DateTimeZone('Europe/Tallinn');
$utc = new DateTimeZone('UTC');

// agent_meta timma_user_id -> lp agent id
$agentByTimma = [];
foreach ($wpdb->get_results("SELECT object_id,meta_value FROM {$P}latepoint_agent_meta WHERE meta_key='timma_user_id'") as $r) {
    $agentByTimma[(string) $r->meta_value] = (int) $r->object_id;
}

// --- collect real availability per Timma user across [from, to], month by month ---
// $avail[timmaUserId][date] = [ [startMin,endMin], ... ]
$avail = [];
$names = [];
$cursor = new DateTime(substr($from, 0, 7) . '-01');
$lastMonth = new DateTime(substr($to, 0, 7) . '-01');
while ($cursor <= $lastMonth) {
    $mStart = $cursor->format('Y-m-01');
    $mEnd   = $cursor->format('Y-m-t');
    $data = timma_get("/api/worktimes/customer/{$biz}?start={$mStart}&end={$mEnd}", $token, $email);
    if ($data === null) { exit(1); }
    foreach ($data as $u) {
        $uid = (string) ($u['id'] ?? '');
        if ($uid === '') { continue; }
        $names[$uid] = $u['name'] ?? $uid;
        foreach (($u['days'] ?? []) as $day) {
            if (empty($day['dayStart']) || empty($day['dayEnd'])) { continue; }
            $ds = (new DateTime($day['dayStart'], $utc))->setTimezone($tz);
            $de = (new DateTime($day['dayEnd'], $utc))->setTimezone($tz);
            $date = $ds->format('Y-m-d');
            if ($date < $from || $date > $to) { continue; }
            $startMin = (int) $ds->format('H') * 60 + (int) $ds->format('i');
            $endMin   = (int) $de->format('H') * 60 + (int) $de->format('i');
            if ($de->format('Y-m-d') !== $date) { $endMin = 24 * 60; } // overnight -> clamp to midnight
            if ($endMin <= $startMin) { continue; }
            $avail[$uid][$date][] = [$startMin, $endMin];
        }
    }
    $cursor->modify('+1 month');
}

$st = ['agents' => 0, 'work_rows' => 0, 'baseline_rows' => 0, 'skip_noagent' => 0, 'skip_nodays' => 0];
$rowsBuf = [];
$now = (new DateTime('now', $tz))->format('Y-m-d H:i:s');

function flush_rows(&$buf, $wpdb, $P) {
    if (!$buf) { return; }
    $wpdb->query("INSERT INTO {$P}latepoint_work_periods (agent_id,service_id,location_id,start_time,end_time,week_day,custom_date,chain_id,created_at,updated_at) VALUES " . implode(',', $buf));
    $buf = [];
}

foreach ($agentByTimma as $uid => $agentId) {
    $days = $avail[$uid] ?? [];
    $workDayCount = count($days);
    $st['agents']++;
    echo sprintf("  agent #%-3d %-20s : %d working days in range\n", $agentId, $names[$uid] ?? $uid, $workDayCount);
    if ($workDayCount === 0) { $st['skip_nodays']++; }

    if (!$dryRun) {
        $wpdb->delete("{$P}latepoint_work_periods", ['agent_id' => $agentId, 'chain_id' => 'timma_import']);
    }

    // weekly baseline: off by default (weight 1 beats global default weight 0)
    for ($wd = 1; $wd <= 7; $wd++) {
        $st['baseline_rows']++;
        if (!$dryRun) {
            $rowsBuf[] = $wpdb->prepare("(%d,0,0,0,0,%d,NULL,'timma_import',%s,%s)", $agentId, $wd, $now, $now);
        }
    }

    // real working-day windows (custom_date rows, weight 4 -> win for that date)
    foreach ($days as $date => $windows) {
        $wd = (int) (new DateTime($date, $tz))->format('N');
        foreach ($windows as [$startMin, $endMin]) {
            $st['work_rows']++;
            if (!$dryRun) {
                $rowsBuf[] = $wpdb->prepare("(%d,0,0,%d,%d,%d,%s,'timma_import',%s,%s)", $agentId, $startMin, $endMin, $wd, $date, $now, $now);
                if (count($rowsBuf) >= 500) { flush_rows($rowsBuf, $wpdb, $P); }
            }
        }
    }
}
if (!$dryRun) { flush_rows($rowsBuf, $wpdb, $P); }

// ---- agent <-> service connections (so agents are bookable) ----
// Data-driven: connect each agent to the services/locations they actually have
// bookings for. Idempotent via OsConnectorHelper::save_connection.
$st['connections'] = 0; $st['conn_new'] = 0;
if (class_exists('OsConnectorHelper')) {
    $pairs = $wpdb->get_results("SELECT DISTINCT agent_id, service_id, location_id
        FROM {$P}latepoint_bookings WHERE agent_id>0 AND service_id>0 AND location_id>0");
    foreach ($pairs as $pr) {
        $conn = ['agent_id' => (int) $pr->agent_id, 'service_id' => (int) $pr->service_id, 'location_id' => (int) $pr->location_id];
        $st['connections']++;
        if (!OsConnectorHelper::has_connection($conn)) {
            $st['conn_new']++;
            if (!$dryRun) { OsConnectorHelper::save_connection($conn); }
        }
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Agents processed: %d (no working days in range: %d, unmatched Timma users skipped)\n", $st['agents'], $st['skip_nodays']);
echo sprintf("Working-day rows: %d | weekly day-off baseline rows: %d\n", $st['work_rows'], $st['baseline_rows']);
echo sprintf("Agent-service-location connections: %d ensured (%d newly created)\n", $st['connections'], $st['conn_new']);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
