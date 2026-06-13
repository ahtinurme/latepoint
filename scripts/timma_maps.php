<?php
/**
 * Shared mapping helpers for the Timma -> LatePoint customers/bookings/orders import.
 * Resolves Timma serviceIds and agent userIds to LatePoint ids against the LIVE db.
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Timma booking serviceId -> a CANONICAL catalog serviceId that the services import
 * stamped as service_meta `timma_service_id`. Folds package(*)/deleted variants onto
 * their real service. Anything not listed resolves by its own id, then by name, then
 * is created on demand.
 */
const TIMMA_CANON_SERVICE = [
    // EMS personal (solo)
    5004 => 5004, 9551 => 5004, 9552 => 5004, 9559 => 5004, 5002 => 5004,
    // EMS first-time solo
    3301 => 3301, 5001 => 3301,
    // EMS duo
    5003 => 5003, 9501 => 5003, 9560 => 5003,
    // EMS first-time duo
    3302 => 3302, 9502 => 3302,
    // Yoga personal
    9001 => 9001, 9002 => 9001,
    // Yoga group
    9003 => 9003, 9004 => 9003,
    // Yoga group first-time
    9005 => 9005,
    // Joogatreening (legacy generic)
    1160 => 1160,
    // Infrared mat
    1151 => 1151, 1152 => 1151, 9555 => 1151,
    // Cavitation 1 / 2
    1154 => 1154, 9554 => 1154, 1157 => 1157, 9558 => 1157,
    // RF lifting 1 / 2 (+ their starred pkg)
    1153 => 1153, 9553 => 1153, 1158 => 1158, 9557 => 1158,
    // RF face / face+decollete (own services)
    1155 => 1155, 1156 => 1156,
    // Lipolaser
    1159 => 1159, 9556 => 1159,
    // Massage 30 / 60 / 90 (+ kobido folded to nearest length)
    2001 => 2001, 9561 => 2001,
    2002 => 2002, 2005 => 2002, 2008 => 2002, 2011 => 2002, 9562 => 2002,
    2003 => 2003, 2004 => 2003, 2006 => 2003, 2007 => 2003, 2009 => 2003, 9563 => 2003,
    // 9601 Toitumisnõustamine -> not in catalog, created on demand by name
];

function tm_norm(string $s): string {
    return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s, 'UTF-8'));
}

function tm_category_for(string $name): string {
    $n = mb_strtolower($name, 'UTF-8');
    if (mb_strpos($n, 'jooga') !== false) return 'Jooga';
    if (mb_strpos($n, 'ems') !== false) return 'EMS';
    if (mb_strpos($n, 'massaaž') !== false || mb_strpos($n, 'kobido') !== false) return 'Massaaž';
    if (mb_strpos($n, 'toitumis') !== false) return 'Muud iluteenused';
    return 'Muud iluteenused';
}

function tm_category_id(string $name, bool $dryRun): int {
    global $wpdb, $P;
    static $cache = [];
    $cat = tm_category_for($name);
    if (isset($cache[$cat])) return $cache[$cat];
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$P}latepoint_service_categories WHERE name=%s LIMIT 1", $cat));
    if ($id) return $cache[$cat] = (int) $id;
    if ($dryRun) return $cache[$cat] = 0;
    $m = new OsServiceCategoryModel(); $m->name = $cat; $m->save();
    return $cache[$cat] = (int) $m->id;
}

/** Resolve a Timma serviceId (+ booking-time name) to a LatePoint service id. Creates if needed. */
function tm_resolve_service_id($timmaSid, string $name, bool $dryRun): int {
    global $wpdb, $P;
    static $byMeta = null, $byName = null;
    if ($byMeta === null) {
        $byMeta = [];
        foreach ($wpdb->get_results("SELECT object_id, meta_value FROM {$P}latepoint_service_meta WHERE meta_key='timma_service_id'") as $r) {
            $byMeta[(string) $r->meta_value] = (int) $r->object_id;
        }
        $byName = [];
        foreach ($wpdb->get_results("SELECT id, name FROM {$P}latepoint_services") as $r) {
            $byName[tm_norm($r->name)] = (int) $r->id;
        }
    }
    $canon = (string) (TIMMA_CANON_SERVICE[(int) $timmaSid] ?? $timmaSid);
    if (isset($byMeta[$canon])) return $byMeta[$canon];
    $nn = tm_norm($name);
    if ($nn !== '' && isset($byName[$nn])) return $byName[$nn];
    // create on demand
    if ($dryRun) return 0;
    $m = new OsServiceModel();
    $m->name = $name !== '' ? $name : ('Timma service ' . $timmaSid);
    $m->charge_amount = 0;
    $m->duration = 60;
    $m->category_id = tm_category_id($m->name, $dryRun) ?: null;
    $m->status = LATEPOINT_SERVICE_STATUS_ACTIVE;
    $m->visibility = LATEPOINT_SERVICE_VISIBILITY_VISIBLE;
    $m->capacity_min = 1; $m->capacity_max = 1;
    $m->save();
    OsMetaHelper::save_service_meta_by_key('timma_service_id', (string) $timmaSid, $m->id);
    $byMeta[(string) $timmaSid] = (int) $m->id;
    $byName[tm_norm($m->name)] = (int) $m->id;
    echo "    + created service '{$m->name}' (#{$m->id}) for timma {$timmaSid}\n";
    return (int) $m->id;
}

/**
 * Resolve a Timma agent userId to a LatePoint agent id.
 * meta timma_user_id -> real staff (create, disabled) -> generic "Stuudio" agent.
 * $staff = map userId => ['name'=>, 'email'=>].
 */
function tm_resolve_agent_id(string $userId, array $staff, bool $dryRun): int {
    global $wpdb, $P;
    static $byMeta = null;
    if ($byMeta === null) {
        $byMeta = [];
        foreach ($wpdb->get_results("SELECT object_id, meta_value FROM {$P}latepoint_agent_meta WHERE meta_key='timma_user_id'") as $r) {
            $byMeta[(string) $r->meta_value] = (int) $r->object_id;
        }
    }
    if (isset($byMeta[$userId])) return $byMeta[$userId];

    $info  = $staff[$userId] ?? null;
    $email = $info ? mb_strtolower(trim((string) ($info['email'] ?? ''))) : '';
    $name  = $info ? trim((string) ($info['name'] ?? '')) : '';
    $isRealPerson = ($email !== '' && strpos($email, '@') !== false
        && !in_array(mb_strtolower($name), ['vaba kasutaja', 'massaaži test kasutaja'], true));

    if ($isRealPerson) {
        if ($dryRun) return 0;
        $parts = preg_split('/\s+/', $name, 2);
        $m = new OsAgentModel();
        $m->first_name = $parts[0] ?? $name;
        $m->last_name  = $parts[1] ?? '';
        $m->email = $email;
        $m->display_name = trim(($parts[0] ?? $name) . ' (endine)'); // former staff
        $m->status = defined('LATEPOINT_AGENT_STATUS_DISABLED') ? LATEPOINT_AGENT_STATUS_DISABLED : 'disabled';
        $m->save();
        OsMetaHelper::save_agent_meta_by_key('timma_user_id', $userId, $m->id);
        $byMeta[$userId] = (int) $m->id;
        echo "    + created former-staff agent '{$name}' (#{$m->id}, disabled)\n";
        return (int) $m->id;
    }
    return tm_generic_agent_id($dryRun);
}

function tm_generic_agent_id(bool $dryRun): int {
    global $wpdb, $P;
    static $id = null;
    if ($id !== null) return $id;
    $found = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$P}latepoint_agents WHERE display_name=%s OR first_name=%s LIMIT 1", 'Stuudio', 'Stuudio'));
    if ($found) return $id = (int) $found;
    if ($dryRun) return $id = 0;
    $m = new OsAgentModel();
    $m->first_name = 'Stuudio'; $m->last_name = '';
    $m->email = 'stuudio@yumefit.invalid';
    $m->display_name = 'Stuudio';
    $m->status = defined('LATEPOINT_AGENT_STATUS_DISABLED') ? LATEPOINT_AGENT_STATUS_DISABLED : 'disabled';
    $m->save();
    echo "    + created generic 'Stuudio' agent (#{$m->id})\n";
    return $id = (int) $m->id;
}

function tm_location_id(): int {
    global $wpdb, $P;
    static $id = null;
    if ($id !== null) return $id;
    return $id = (int) ($wpdb->get_var("SELECT id FROM {$P}latepoint_locations ORDER BY id ASC LIMIT 1") ?: 0);
}

/** UTC ISO -> [localDate 'Y-m-d', minutesFromMidnight, utc 'Y-m-d H:i:s']. */
function tm_local(string $utcIso): array {
    $dt = new DateTime($utcIso); // trailing Z => UTC
    $utc = $dt->format('Y-m-d H:i:s');
    $dt->setTimezone(new DateTimeZone('Europe/Tallinn'));
    return [$dt->format('Y-m-d'), ((int) $dt->format('H')) * 60 + (int) $dt->format('i'), $utc];
}

/** Map a Timma slot to a LatePoint booking status. */
function tm_booking_status(array $slot, string $nowUtc): string {
    $note = mb_strtolower((string) ($slot['reservation']['bookingNote'] ?? ''));
    if (preg_match('/ei ilmun|kohale ei|no.?show/u', $note)) {
        return defined('LATEPOINT_BOOKING_STATUS_NO_SHOW') ? LATEPOINT_BOOKING_STATUS_NO_SHOW : 'no_show';
    }
    if (!empty($slot['deletedOn']) || !empty($slot['cancellationReason'])) {
        return defined('LATEPOINT_BOOKING_STATUS_CANCELLED') ? LATEPOINT_BOOKING_STATUS_CANCELLED : 'cancelled';
    }
    $future = !empty($slot['start']) && $slot['start'] > $nowUtc;
    if (($slot['status'] ?? '') === 'payment') {
        return defined('LATEPOINT_BOOKING_STATUS_PAYMENT_PENDING') ? LATEPOINT_BOOKING_STATUS_PAYMENT_PENDING : 'payment_pending';
    }
    return $future
        ? (defined('LATEPOINT_BOOKING_STATUS_APPROVED') ? LATEPOINT_BOOKING_STATUS_APPROVED : 'approved')
        : (defined('LATEPOINT_BOOKING_STATUS_COMPLETED') ? LATEPOINT_BOOKING_STATUS_COMPLETED : 'completed');
}
