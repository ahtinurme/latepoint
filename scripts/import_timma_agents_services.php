#!/usr/bin/env php
<?php
/**
 * Sync agents and services from Timma into LatePoint.
 *
 * Idempotent: safe to run repeatedly. Each Timma entity is matched to a LatePoint
 * record by, in order: a stored Timma-id meta key, then email (agents) / normalized
 * name (services), then created if still not found. The Timma id is saved as meta
 * (timma_user_id / timma_service_id) so subsequent runs update in place.
 *
 * The Timma auth token rotates, so it is passed in at run time.
 *
 * Usage:
 *   php import_timma_agents_services.php --token=<x-auth-token> [options]
 *
 * Options:
 *   --token=...     Timma x-auth-token (required)
 *   --email=...     Timma x-auth-email (default: carmen.kaljula@gmail.com)
 *   --biz=...       Timma business/customer id (default: 673e4475236e6416d9c1fa32)
 *   --what=all|agents|services|packages   What to sync (default: all)
 *   --connect       Connect newly created agents to every active service+location
 *                   (default: OFF — new agents are created but not made bookable,
 *                    to avoid wrong availability on the live site)
 *   --dry-run       Show what would change without writing to the database
 *
 * Run from anywhere; it boots WordPress from this file's location.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$opts = getopt('', ['token:', 'email:', 'biz:', 'what:', 'connect', 'dry-run']);

$token = $opts['token'] ?? getenv('TIMMA_TOKEN') ?: '';
$email = $opts['email'] ?? 'carmen.kaljula@gmail.com';
$biz   = $opts['biz']   ?? '673e4475236e6416d9c1fa32';
$what  = $opts['what']  ?? 'all';
$connect = isset($opts['connect']);
$dryRun  = isset($opts['dry-run']);

if ($token === '') {
    fwrite(STDERR, "ERROR: --token=<x-auth-token> is required (get it from a logged-in pro.timma.ee request header).\n");
    exit(1);
}

// --- boot WordPress (scripts/ -> plugins -> wp-content -> wp root) ---
$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad || !is_file($wpLoad)) {
    fwrite(STDERR, "ERROR: could not locate wp-load.php at " . __DIR__ . "/../../../wp-load.php\n");
    exit(1);
}
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? 'yumefit.ee';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define('WP_USE_THEMES', false);
require $wpLoad;

if (!class_exists('OsAgentModel') || !class_exists('OsServiceModel')) {
    fwrite(STDERR, "ERROR: LatePoint classes not loaded — is the plugin active on this site?\n");
    exit(1);
}

global $wpdb;
$P = $wpdb->prefix;

echo "================ Timma → LatePoint sync ================\n";
echo "DB host : " . DB_HOST . "\n";
echo "DB name : " . DB_NAME . "\n";
echo "Mode    : " . ($dryRun ? 'DRY-RUN (no writes)' : 'LIVE (writing changes)') . "\n";
echo "Syncing : {$what}" . ($connect ? "  (+connect new agents)" : "") . "\n";
echo "========================================================\n\n";

// ---------- helpers ----------

function timma_get(string $path, string $token, string $email): array {
    $res = wp_remote_get('https://pro.timma.ee' . $path, [
        'headers' => ['x-auth-token' => $token, 'x-auth-email' => $email, 'accept' => 'application/json'],
        'timeout' => 30,
    ]);
    if (is_wp_error($res)) {
        fwrite(STDERR, "Timma request failed: " . $res->get_error_message() . "\n");
        exit(1);
    }
    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    if ($code !== 200) {
        fwrite(STDERR, "Timma {$path} returned HTTP {$code}. Token may be expired.\n");
        exit(1);
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        fwrite(STDERR, "Timma {$path} returned non-JSON.\n");
        exit(1);
    }
    return $json;
}

function normalize_name(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
}

// LatePoint service category: get id by name, create if missing.
function category_id_for(string $serviceName, bool $dryRun): int {
    static $cache = [];
    $n = mb_strtolower($serviceName, 'UTF-8');
    if (mb_strpos($n, 'jooga') !== false)                                  $cat = 'Jooga';
    elseif (mb_strpos($n, 'ems') !== false)                                $cat = 'EMS';
    elseif (mb_strpos($n, 'massaaž') !== false || mb_strpos($n, 'kobido') !== false) $cat = 'Massaaž';
    else                                                                   $cat = 'Muud iluteenused';

    if (isset($cache[$cat])) return $cache[$cat];

    $existing = (new OsServiceCategoryModel())->where(['name' => $cat])->set_limit(1)->get_results_as_models();
    if ($existing) {
        return $cache[$cat] = (int) $existing->id;
    }
    if ($dryRun) {
        echo "    [dry-run] would CREATE category '{$cat}'\n";
        return $cache[$cat] = -1;
    }
    $model = new OsServiceCategoryModel();
    $model->name = $cat;
    $model->save();
    echo "    created category '{$cat}' (#{$model->id})\n";
    return $cache[$cat] = (int) $model->id;
}

// Find an agent id by stored timma_user_id meta.
function find_agent_by_timma_id(string $timmaId): ?int {
    global $wpdb, $P;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT object_id FROM {$P}latepoint_agent_meta WHERE meta_key='timma_user_id' AND meta_value=%s LIMIT 1",
        $timmaId
    ));
    return $id ? (int) $id : null;
}
function find_service_by_timma_id(string $timmaId): ?int {
    global $wpdb, $P;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT object_id FROM {$P}latepoint_service_meta WHERE meta_key='timma_service_id' AND meta_value=%s LIMIT 1",
        $timmaId
    ));
    return $id ? (int) $id : null;
}

// ---------- SERVICES ----------

function sync_services(string $biz, string $token, string $email, bool $dryRun): array {
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
    $catalog = timma_get("/api/customerservices/customer/{$biz}", $token, $email);
    echo "SERVICES — Timma catalog: " . count($catalog) . "\n";

    // preload existing LP services for name matching
    $existingByName = [];
    foreach ((new OsServiceModel())->get_results_as_models() ?: [] as $svc) {
        $existingByName[normalize_name($svc->name)] = $svc;
    }

    foreach ($catalog as $svc) {
        $sid     = (string) ($svc['serviceId'] ?? '');
        $nameEt  = $svc['nameEt'] ?? $svc['nameEn'] ?? $svc['shortName'] ?? '';
        $subCat  = $svc['subCategoryName'] ?? '';
        $name    = trim((string) $nameEt);

        // skip non-bookable / internal / disabled / package-product entries
        $skipReason = '';
        if ($subCat === 'Isiklik/Pausid')                          $skipReason = 'personal/break';
        elseif (mb_stripos($name, 'ei ole kasutusel') === 0)       $skipReason = 'disabled';
        elseif (mb_strpos($name, '*') === 0)                       $skipReason = 'package-duplicate (*)';
        elseif (preg_match('/\b\d+\s*x\b|pakett|kaart/ui', $name))  $skipReason = 'package product (Nx/pakett/kaart)';
        elseif ($name === '')                                      $skipReason = 'no name';
        if ($skipReason !== '') {
            $stats['skipped']++;
            continue;
        }

        $price    = round(((int) ($svc['price'] ?? 0)) / 100, 2);
        $duration = (int) ($svc['duration'] ?? $svc['clientDuration'] ?? 60);
        $catId    = category_id_for($name, $dryRun);

        // match: timma id meta -> normalized name
        $existingId = find_service_by_timma_id($sid);
        $model = null;
        if ($existingId) {
            $model = new OsServiceModel($existingId);
        } elseif (isset($existingByName[normalize_name($name)])) {
            $model = $existingByName[normalize_name($name)];
        }

        if ($model && $model->id) {
            // update price/duration/category; keep curated name; ensure meta
            $changes = [];
            if ((float) $model->charge_amount !== (float) $price) { $changes[] = "price {$model->charge_amount}→{$price}"; $model->charge_amount = $price; }
            if ((int) $model->duration !== $duration)             { $changes[] = "duration {$model->duration}→{$duration}"; $model->duration = $duration; }
            if ($catId > 0 && (int) $model->category_id !== $catId){ $changes[] = "category→{$catId}"; $model->category_id = $catId; }
            $label = "  update svc #{$model->id} \"{$model->name}\" [timma {$sid}]";
            if ($changes) $label .= ": " . implode(', ', $changes);
            echo $label . "\n";
            if (!$dryRun) {
                if ($changes) $model->save();
                OsMetaHelper::save_service_meta_by_key('timma_service_id', $sid, $model->id);
            }
            $stats['updated']++;
        } else {
            echo "  CREATE svc \"{$name}\" (€{$price}, {$duration}min, cat {$catId}) [timma {$sid}]\n";
            if (!$dryRun) {
                $m = new OsServiceModel();
                $m->name = $name;
                $m->charge_amount = $price;
                $m->duration = $duration;
                $m->category_id = $catId > 0 ? $catId : null;
                $m->status = LATEPOINT_SERVICE_STATUS_ACTIVE;
                $m->visibility = LATEPOINT_SERVICE_VISIBILITY_VISIBLE;
                $m->capacity_min = 1;
                $m->capacity_max = 1;
                if ($m->save()) {
                    OsMetaHelper::save_service_meta_by_key('timma_service_id', $sid, $m->id);
                    $existingByName[normalize_name($name)] = $m;
                } else {
                    echo "    !! save failed: " . implode('; ', $m->get_error_messages()) . "\n";
                }
            }
            $stats['created']++;
        }
    }
    return $stats;
}

// ---------- AGENTS ----------

function sync_agents(string $biz, string $token, string $email, bool $dryRun, bool $connect): array {
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
    $staff = timma_get("/api/users/customer/{$biz}?includeRemoved=true", $token, $email);
    echo "AGENTS — Timma staff: " . count($staff) . "\n";

    // names that are not real people
    $nameBlocklist = ['vaba kasutaja', 'massaaži test kasutaja'];

    // preload existing agents for email/name matching
    $byEmail = [];
    $byName  = [];
    foreach ((new OsAgentModel())->get_results_as_models() ?: [] as $a) {
        if ($a->email) $byEmail[mb_strtolower(trim($a->email))] = $a;
        $byName[normalize_name(trim($a->first_name . ' ' . $a->last_name))] = $a;
    }

    $newAgentIds = [];
    foreach ($staff as $u) {
        $uid   = (string) ($u['id'] ?? $u['_id'] ?? '');
        $em    = mb_strtolower(trim((string) ($u['email'] ?? '')));
        $full  = trim((string) ($u['name'] ?? ''));
        // Timma stores a single full name; split into first + rest.
        $parts = preg_split('/\s+/', $full, 2);
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? '';

        // skip removed staff, resource/system accounts (UUID emails, no @), and placeholders
        if (!empty($u['deletedOn'])) { $stats['skipped']++; continue; }
        if (strpos($em, '@') === false || in_array(mb_strtolower($full), $nameBlocklist, true)) {
            $stats['skipped']++;
            continue;
        }

        // match: timma id meta -> email -> normalized name
        $model = null;
        $existingId = find_agent_by_timma_id($uid);
        if ($existingId) {
            $model = new OsAgentModel($existingId);
        } elseif ($em && isset($byEmail[$em])) {
            $model = $byEmail[$em];
        } elseif (isset($byName[normalize_name($full)])) {
            $model = $byName[normalize_name($full)];
        }

        $phone = trim((string) ($u['phone'] ?? ''));

        if ($model && $model->id) {
            $changes = [];
            if ($first && $model->first_name !== $first) { $changes[] = "first {$model->first_name}→{$first}"; $model->first_name = $first; }
            if ($last  && $model->last_name  !== $last)  { $changes[] = "last {$model->last_name}→{$last}";   $model->last_name = $last; }
            if ($phone && empty($model->phone))          { $changes[] = "phone+";                            $model->phone = $phone; }
            // do NOT overwrite email or display_name on existing agents (may be customized)
            echo "  update agent #{$model->id} \"{$full}\" [timma {$uid}]" . ($changes ? ": " . implode(', ', $changes) : "") . "\n";
            if (!$dryRun) {
                if ($changes) $model->save();
                OsMetaHelper::save_agent_meta_by_key('timma_user_id', $uid, $model->id);
            }
            $stats['updated']++;
        } else {
            $display = trim($first . ' (EST/ENG)');
            echo "  CREATE agent \"{$full}\" <{$em}> display=\"{$display}\" [timma {$uid}]\n";
            if (!$dryRun) {
                $m = new OsAgentModel();
                $m->first_name = $first;
                $m->last_name = $last;
                $m->email = $em;
                $m->phone = $phone;
                $m->display_name = $display;
                $m->status = LATEPOINT_AGENT_STATUS_ACTIVE;
                if ($m->save()) {
                    OsMetaHelper::save_agent_meta_by_key('timma_user_id', $uid, $m->id);
                    $newAgentIds[] = (int) $m->id;
                } else {
                    echo "    !! save failed: " . implode('; ', $m->get_error_messages()) . "\n";
                }
            }
            $stats['created']++;
        }
    }

    if ($connect && $newAgentIds && !$dryRun) {
        global $wpdb, $P;
        $serviceIds  = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$P}latepoint_services WHERE status=%s", LATEPOINT_SERVICE_STATUS_ACTIVE)));
        $locationIds = array_map('intval', $wpdb->get_col("SELECT id FROM {$P}latepoint_locations"));
        $n = 0;
        foreach ($newAgentIds as $aid) {
            foreach ($serviceIds as $svcId) {
                foreach ($locationIds as $locId) {
                    OsConnectorHelper::save_connection(['agent_id' => $aid, 'service_id' => $svcId, 'location_id' => $locId]);
                    $n++;
                }
            }
        }
        echo "  connected {$n} agent↔service↔location links for " . count($newAgentIds) . " new agents\n";
    } elseif ($newAgentIds && !$connect) {
        echo "  NOTE: " . count($newAgentIds) . " new agents created but NOT connected to services/locations.\n";
        echo "        Assign them in LatePoint, or re-run with --connect to attach to all active services.\n";
    }

    return $stats;
}

// ---------- PACKAGES (Timma "Nx kaart" products -> LatePoint bundles) ----------

function sync_packages(string $biz, string $token, string $email, bool $dryRun): array {
    global $wpdb, $P;
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
    $products = timma_get("/api/products/stock/customer/{$biz}", $token, $email);
    echo "PACKAGES — Timma products: " . count($products) . "\n";

    // LatePoint services: normalized name -> id (for mapping the package's base service)
    $svcByNorm = [];
    foreach ($wpdb->get_results("SELECT id, name FROM {$P}latepoint_services") as $s) {
        $svcByNorm[normalize_name($s->name)] = (int) $s->id;
    }

    foreach ($products as $prod) {
        $name = trim((string) ($prod['name'] ?? ''));
        $pid  = (string) ($prod['id'] ?? '');
        // only package cards/series
        if (!preg_match('/kaart|pakett|kuukaart/ui', $name)) continue;

        $price = round(((int) ($prod['price'] ?? 0)) / 100, 2);
        if (!preg_match('/(\d+)\s*x/ui', $name, $m)) {
            echo "  SKIP (no fixed quantity — monthly/gift): \"{$name}\" €{$price}\n";
            $stats['skipped']++;
            continue;
        }
        $qty  = (int) $m[1];
        $base = trim(preg_replace('/\s*\d+\s*x\s*(kaart|pakett).*$/ui', '', $name));
        $svcId = $svcByNorm[normalize_name($base)] ?? 0;

        if (!$svcId) {
            echo "  SKIP (any-service / unmatched base \"{$base}\" — set up manually as a multi-service bundle): \"{$name}\"\n";
            $stats['skipped']++;
            continue;
        }

        // idempotent: match an existing bundle by name, else by same service+quantity
        // (the studio may already have an equivalent bundle under a shorter name).
        $existing = (new OsBundleModel())->where(['name' => $name])->set_limit(1)->get_results_as_models();
        if (!$existing) {
            $dupId = $wpdb->get_var($wpdb->prepare(
                "SELECT bs.bundle_id FROM {$P}latepoint_bundles_services bs
                 JOIN {$P}latepoint_bundles b ON b.id = bs.bundle_id
                 WHERE bs.service_id=%d AND bs.quantity=%d LIMIT 1",
                $svcId, $qty
            ));
            if ($dupId) $existing = new OsBundleModel((int) $dupId);
        }
        $svcDuration = (int) ($wpdb->get_var($wpdb->prepare("SELECT duration FROM {$P}latepoint_services WHERE id=%d", $svcId)) ?: 60);

        if ($existing && $existing->id) {
            echo "  update bundle #{$existing->id} \"{$name}\": €{$existing->charge_amount}→{$price}, {$qty}x service#{$svcId}\n";
            if (!$dryRun) {
                $existing->charge_amount = $price;
                $existing->save();
                $existing->save_services(['service_' . $svcId => ['quantity' => $qty, 'total_attendees' => 1, 'duration' => $svcDuration, 'connected' => 'yes']]);
            }
            $stats['updated']++;
        } else {
            echo "  CREATE bundle \"{$name}\" = {$qty}x service#{$svcId} @ €{$price}\n";
            if (!$dryRun) {
                $b = new OsBundleModel();
                $b->name = $name;
                $b->charge_amount = $price;
                $b->status = LATEPOINT_BUNDLE_STATUS_ACTIVE;
                $b->visibility = LATEPOINT_BUNDLE_VISIBILITY_VISIBLE;
                if ($b->save()) {
                    $b->save_services(['service_' . $svcId => ['quantity' => $qty, 'total_attendees' => 1, 'duration' => $svcDuration, 'connected' => 'yes']]);
                } else {
                    echo "    !! save failed: " . implode('; ', $b->get_error_messages()) . "\n";
                }
            }
            $stats['created']++;
        }
    }
    return $stats;
}

// ---------- run ----------

$svcStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
$agStats  = ['created' => 0, 'updated' => 0, 'skipped' => 0];
$pkgStats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

if ($what === 'all' || $what === 'services') {
    $svcStats = sync_services($biz, $token, $email, $dryRun);
    echo "\n";
}
if ($what === 'all' || $what === 'agents') {
    $agStats = sync_agents($biz, $token, $email, $dryRun, $connect);
    echo "\n";
}
if ($what === 'all' || $what === 'packages') {
    $pkgStats = sync_packages($biz, $token, $email, $dryRun);
    echo "\n";
}

echo "==================== SUMMARY ====================\n";
echo sprintf("Services: created %d, updated %d, skipped %d\n", $svcStats['created'], $svcStats['updated'], $svcStats['skipped']);
echo sprintf("Agents  : created %d, updated %d, skipped %d\n", $agStats['created'], $agStats['updated'], $agStats['skipped']);
echo sprintf("Packages: created %d, updated %d, skipped %d\n", $pkgStats['created'], $pkgStats['updated'], $pkgStats['skipped']);
echo ($dryRun ? "DRY-RUN — no changes were written.\n" : "Done.\n");
