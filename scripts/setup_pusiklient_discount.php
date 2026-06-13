#!/usr/bin/env php
<?php
/**
 * Create/refresh the "püsiklient" (loyal customer) discount coupon and register its
 * code in the option the latepoint-yumefit-rules plugin auto-applies.
 *
 * The coupon is a PERCENT discount applied to all services (no service/customer
 * rules). Eligibility is enforced by the plugin (is_pusiklient meta), not by the
 * coupon — so there is no static customer list to maintain. The percentage is
 * configurable: pass --percent or just edit the coupon's value in LatePoint admin.
 *
 * Usage: php -d memory_limit=1024M setup_pusiklient_discount.php [--percent=15]
 *          [--code=PUSIKLIENT] [--name="Püsikliendi soodustus"] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['percent:', 'code:', 'name:', 'dry-run']);
$percent = isset($opts['percent']) ? (float) $opts['percent'] : 15;
$codeArg = isset($opts['code']) ? strtoupper(trim($opts['code'])) : null; // explicit override only
$name    = $opts['name'] ?? 'Püsikliendi soodustus';
$dryRun  = isset($opts['dry-run']);

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsCouponModel')) { fwrite(STDERR, "LatePoint Pro (coupons) not loaded\n"); exit(1); }

echo "=========== Püsiklient discount coupon ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | percent={$percent}%" . ($codeArg ? " code={$codeArg}" : "") . "\n";
echo "==================================================\n";

// Locate the existing coupon: by stored id, then stored code, then --code.
$coupon = null;
$storedId = (int) get_option('yumefit_pusiklient_coupon_id', 0);
if ($storedId) { $c = new OsCouponModel($storedId); if ($c && $c->id) $coupon = $c; }
if (!$coupon) {
    $byCode = $codeArg ?: (string) get_option('yumefit_pusiklient_coupon_code', '');
    if ($byCode) { $f = (new OsCouponModel())->where(['code' => $byCode])->set_limit(1)->get_results_as_models(); if ($f && $f->id) $coupon = $f; }
}
$isNew = !$coupon;
if ($isNew) { $coupon = new OsCouponModel(); }

// Resolve code: explicit --code wins; keep an existing code; else generate an
// UNGUESSABLE random one so the coupon can never be added manually by accident.
if ($codeArg) {
    $code = $codeArg;
} elseif (!$isNew && !empty($coupon->code)) {
    $code = $coupon->code;
} else {
    $code = 'PK-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', wp_generate_password(24, false)), 0, 14));
}

echo ($isNew ? "CREATE" : "UPDATE #{$coupon->id}") . " coupon '{$code}' = {$percent}% off all services\n";

if (!$dryRun) {
    $coupon->code = $code;
    $coupon->name = $name;
    $coupon->discount_type = 'percent';
    $coupon->discount_value = $percent;
    $coupon->status = defined('LATEPOINT_COUPON_STATUS_ACTIVE') ? LATEPOINT_COUPON_STATUS_ACTIVE : 'active';
    if (empty($coupon->rules)) { $coupon->rules = wp_json_encode(new stdClass()); } // no restrictions -> all services
    if (!$coupon->save()) {
        fwrite(STDERR, "Coupon save failed: " . implode('; ', $coupon->get_error_messages()) . "\n");
        exit(1);
    }
    update_option('yumefit_pusiklient_coupon_code', $code, false);
    update_option('yumefit_pusiklient_coupon_id', (int) $coupon->id, false);
    echo "Saved coupon #{$coupon->id}; auto-applied only (random code, not meant to be typed)\n";
}

echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done. (Edit the % anytime in LatePoint → Coupons, or re-run with --percent.)\n");
