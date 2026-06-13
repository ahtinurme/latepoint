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
$code    = strtoupper(trim($opts['code'] ?? 'PUSIKLIENT'));
$name    = $opts['name'] ?? 'Püsikliendi soodustus';
$dryRun  = isset($opts['dry-run']);

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsCouponModel')) { fwrite(STDERR, "LatePoint Pro (coupons) not loaded\n"); exit(1); }

echo "=========== Püsiklient discount coupon ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | code={$code} percent={$percent}%\n";
echo "==================================================\n";

$existing = (new OsCouponModel())->where(['code' => $code])->set_limit(1)->get_results_as_models();
$coupon = ($existing && $existing->id) ? $existing : new OsCouponModel();
$isNew = empty($coupon->id);

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
    echo "Saved coupon #{$coupon->id}; option yumefit_pusiklient_coupon_code = {$code}\n";
}

echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done. (Edit the % anytime in LatePoint → Coupons, or re-run with --percent.)\n");
