#!/usr/bin/env php
<?php
/**
 * Import agent work schedules from Timma into LatePoint work_periods.
 *
 * Timma `workTimes` is a 7-entry weekly template (Mon..Sun; each {start,end,enabled}).
 * We expand it into DATE-SPECIFIC work_periods for every date in [--from, --to],
 * so availability is bounded to start on the requested historical date (default
 * 2026-02-01) and runs through the forward booking window (default today + 90 days).
 *
 * - Agents matched via agent_meta `timma_user_id`; their is_custom_hours is set to 1
 *   so LatePoint uses these instead of the global default schedule.
 * - Rows tagged chain_id='timma_import'; a re-run deletes & rebuilds those (idempotent).
 * - Also ensures agent<->service<->location connections (so agents are bookable),
 *   data-driven from each agent's actual imported bookings. Idempotent.
 *
 * Usage: php -d memory_limit=1024M import_timma_work_schedules.php --token=... \
 *          [--from=2026-02-01] [--to=YYYY-MM-DD] [--email=] [--biz=] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['token:', 'email:', 'biz:', 'from:', 'to:', 'dry-run']);
$token = $opts['token'] ?? getenv('TIMMA_TOKEN') ?: '';
$email = $opts['email'] ?? 'carmen.kaljula@gmail.com';
$biz   = $opts['biz']   ?? '673e4475236e6416d9c1fa32';
$from  = $opts['from']  ?? '2026-02-01';
$to    = $opts['to']    ?? (new DateTime('now'))->modify('+90 days')->format('Y-m-d');
$dryRun = isset($opts['dry-run']);
if ($token === '') { fwrite(STDERR, "ERROR: --token required\n"); exit(1); }

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsAgentModel')) { fwrite(STDERR, "LatePoint not loaded\n"); exit(1); }
global $wpdb; $P = $wpdb->prefix;

echo "=========== Import agent work schedules ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | range {$from} .. {$to}\n";
echo "===================================================\n";

function timma_get(string $path, string $token, string $email) {
    $res = wp_remote_get('https://pro.timma.ee' . $path, ['headers' => ['x-auth-token' => $token, 'x-auth-email' => $email, 'accept' => 'application/json'], 'timeout' => 45]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) { fwrite(STDERR, "Timma {$path} failed (token expired?)\n"); return null; }
    return json_decode(wp_remote_retrieve_body($res), true);
}
function hhmmToMin(?string $iso): ?int {
    if ($iso && preg_match('/T(\d{2}):(\d{2})/', $iso, $m)) return ((int) $m[1]) * 60 + (int) $m[2];
    return null;
}

// agent_meta timma_user_id -> lp agent id
$agentByTimma = [];
foreach ($wpdb->get_results("SELECT object_id,meta_value FROM {$P}latepoint_agent_meta WHERE meta_key='timma_user_id'") as $r) $agentByTimma[(string)$r->meta_value] = (int)$r->object_id;

$staff = timma_get("/api/users/customer/{$biz}?includeRemoved=true", $token, $email);
if ($staff === null) { exit(1); }

$st = ['agents'=>0,'rows'=>0,'skip_noagent'=>0,'skip_notemplate'=>0];
$rowsBuf = [];
$now = (new DateTime())->format('Y-m-d H:i:s');

foreach ($staff as $u) {
    $uid = (string) ($u['id'] ?? '');
    $agentId = $agentByTimma[$uid] ?? 0;
    if (!$agentId) { $st['skip_noagent']++; continue; }
    $wt = $u['workTimes'] ?? null;
    if (!is_array($wt) || count($wt) < 7) { $st['skip_notemplate']++; continue; }

    // weekday template: index 0=Mon..6=Sun -> [startMin,endMin] or null
    $tpl = [];
    for ($i = 0; $i < 7; $i++) {
        $w = $wt[$i] ?? null;
        $tpl[$i] = ($w && !empty($w['enabled'])) ? [hhmmToMin($w['start']), hhmmToMin($w['end'])] : null;
    }
    $workDays = count(array_filter($tpl));
    if ($workDays === 0) { $st['skip_notemplate']++; continue; }
    $st['agents']++;
    echo sprintf("  agent #%d %-20s : %d work-days/week\n", $agentId, $u['name'] ?? '', $workDays);

    if (!$dryRun) {
        // refresh: clear previously imported rows for this agent (idempotent).
        // No flag needed: get_work_periods selects agent_id IN [0, agentId] ordered
        // custom_date DESC, agent_id DESC, so these date-specific rows take precedence.
        $wpdb->delete("{$P}latepoint_work_periods", ['agent_id' => $agentId, 'chain_id' => 'timma_import']);
    }

    $d = new DateTime($from); $end = new DateTime($to);
    while ($d <= $end) {
        $isoDow = (int) $d->format('N'); // 1=Mon..7=Sun
        $slot = $tpl[$isoDow - 1];
        if ($slot && $slot[0] !== null && $slot[1] !== null) {
            $st['rows']++;
            if (!$dryRun) {
                $rowsBuf[] = $wpdb->prepare("(%d,0,0,%d,%d,%d,%s,'timma_import',%s,%s)",
                    $agentId, $slot[0], $slot[1], $isoDow, $d->format('Y-m-d'), $now, $now);
                if (count($rowsBuf) >= 500) {
                    $wpdb->query("INSERT INTO {$P}latepoint_work_periods (agent_id,service_id,location_id,start_time,end_time,week_day,custom_date,chain_id,created_at,updated_at) VALUES " . implode(',', $rowsBuf));
                    $rowsBuf = [];
                }
            }
        }
        $d->modify('+1 day');
    }
}
if (!$dryRun && $rowsBuf) {
    $wpdb->query("INSERT INTO {$P}latepoint_work_periods (agent_id,service_id,location_id,start_time,end_time,week_day,custom_date,chain_id,created_at,updated_at) VALUES " . implode(',', $rowsBuf));
}

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
        $exists = OsConnectorHelper::has_connection($conn);
        if (!$exists) {
            $st['conn_new']++;
            if (!$dryRun) OsConnectorHelper::save_connection($conn);
        }
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Agents with schedule imported: %d (no LP agent: %d, no template: %d)\n", $st['agents'], $st['skip_noagent'], $st['skip_notemplate']);
echo sprintf("Date-specific work_period rows: %d\n", $st['rows']);
echo sprintf("Agent-service-location connections: %d ensured (%d newly created)\n", $st['connections'], $st['conn_new']);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
