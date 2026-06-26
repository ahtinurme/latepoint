<?php
/**
 * Plugin Name: LatePoint Yumefit Gift Cards
 * Description: Sell gift cards online. A buyer chooses an amount, pays via EveryPay
 *              (the same gateway LatePoint bookings use), and a one-time voucher code
 *              is generated automatically as a LatePoint fixed-amount coupon and
 *              emailed to the recipient (or buyer). Place the form with the
 *              [yumefit_giftcard_form] shortcode.
 * Version:     1.0.0
 * Author:      Yumefit
 * Text Domain: latepoint-yumefit-giftcards
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Design (see also [[latepoint-pusiklient-discount]] for the coupon primitive):
 * - The buyer picks a specific SERVICE or PACKAGE (bundle) and pays its current price.
 * - The voucher IS a LatePoint coupon scoped to that exact item: discount_type=percent,
 *   discount_value=100, rule {limit_total:1, service_ids|bundle_ids:[id]} → a single-use
 *   code that makes that one service/package free in the normal booking flow. Scoping to
 *   the item (not a number) means it stays valid as "one free X" even if prices change.
 * - Payment reuses OsEverypayApiHelper (LatePoint's EveryPay addon): one-off payment,
 *   server-side verified. Credentials come from LatePoint payment settings — no config here.
 * - Sale records live in our own table; the coupon shows up in LatePoint → Coupons.
 *   ponytail: gift-card revenue is NOT mirrored into LatePoint orders (LatePoint has no
 *   non-appointment product to sell) — it's in this table + the EveryPay dashboard.
 */

define('YUMEFIT_GIFTCARD_TABLE', 'yumefit_giftcards');
define('YUMEFIT_GIFTCARD_VALID_MONTHS', 12); // voucher validity window

function yumefit_giftcard_table(): string {
    global $wpdb;
    return $wpdb->prefix . YUMEFIT_GIFTCARD_TABLE;
}

register_activation_hook(__FILE__, 'yumefit_giftcard_install');
function yumefit_giftcard_install(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = yumefit_giftcard_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_reference VARCHAR(64) NOT NULL,
        payment_reference VARCHAR(128) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        amount DECIMAL(20,4) NOT NULL,
        item_type VARCHAR(20) DEFAULT NULL,
        item_id BIGINT UNSIGNED DEFAULT NULL,
        item_name VARCHAR(190) DEFAULT NULL,
        coupon_id BIGINT UNSIGNED DEFAULT NULL,
        coupon_code VARCHAR(100) DEFAULT NULL,
        buyer_name VARCHAR(190) DEFAULT NULL,
        buyer_email VARCHAR(190) DEFAULT NULL,
        recipient_name VARCHAR(190) DEFAULT NULL,
        recipient_email VARCHAR(190) DEFAULT NULL,
        message TEXT,
        deliver_to_recipient TINYINT(1) NOT NULL DEFAULT 0,
        valid_until DATE DEFAULT NULL,
        created_at DATETIME NOT NULL,
        paid_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_reference (order_reference),
        KEY status (status)
    ) {$charset};");
}

function yumefit_giftcard_action_url(string $action): string {
    return add_query_arg('action', $action, admin_url('admin-post.php'));
}

// "50.0000" → "50", "12.50" → "12.5", "12.55" → "12.55" (drop trailing-zero cents)
function yumefit_giftcard_amount_label($amount): string {
    return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
}

/*
 * Sellable gift items = active services (fixed price) + active packages (bundles),
 * keyed by "service_<id>" / "bundle_<id>". This is the SINGLE source of truth for both
 * rendering the picker and resolving/pricing a submitted choice server-side, so a buyer
 * can never gift a hidden item or a tampered price.
 */
function yumefit_giftcard_items(): array {
    $items = [];
    if (!class_exists('OsServiceModel') || !class_exists('OsBundleModel')) {
        return $items;
    }

    $services = (new OsServiceModel())->should_be_active()->get_results_as_models();
    foreach (is_array($services) ? $services : ($services ? [$services] : []) as $service) {
        if (!empty($service->is_price_variable) || (float) $service->charge_amount <= 0) {
            continue; // can't pre-price a variable-price service
        }
        $items['service_' . $service->id] = [
            'type'  => 'service',
            'id'    => (int) $service->id,
            'name'  => $service->name,
            'price' => (float) $service->charge_amount,
        ];
    }

    $bundles = (new OsBundleModel())->should_be_active()->should_not_be_hidden()->get_results_as_models();
    foreach (is_array($bundles) ? $bundles : ($bundles ? [$bundles] : []) as $bundle) {
        $price = (float) $bundle->full_amount_to_charge();
        if ($price <= 0) {
            continue;
        }
        $items['bundle_' . $bundle->id] = [
            'type'  => 'bundle',
            'id'    => (int) $bundle->id,
            'name'  => $bundle->name,
            'price' => $price,
        ];
    }

    return $items;
}

/* ----------------------------------------------------------------------------
 * Purchase form (shortcode)
 * ------------------------------------------------------------------------- */
add_shortcode('yumefit_giftcard_form', 'yumefit_giftcard_form_shortcode');
function yumefit_giftcard_form_shortcode(): string {
    $error  = isset($_GET['gc_error']) ? sanitize_text_field(wp_unslash($_GET['gc_error'])) : '';
    $notice = isset($_GET['gc_notice']) ? sanitize_text_field(wp_unslash($_GET['gc_notice'])) : '';

    $items     = yumefit_giftcard_items();
    $services  = array_filter($items, fn($i) => $i['type'] === 'service');
    $bundles   = array_filter($items, fn($i) => $i['type'] === 'bundle');
    $price_fmt = fn($p) => class_exists('OsMoneyHelper') ? OsMoneyHelper::format_price($p) : (yumefit_giftcard_amount_label($p) . ' €');

    ob_start();
    ?>
    <div class="yumefit-giftcard">
        <style>
            .yumefit-giftcard{max-width:520px}
            .yumefit-giftcard .gc-row{margin:0 0 14px}
            .yumefit-giftcard label{display:block;font-weight:600;margin:0 0 4px}
            .yumefit-giftcard input[type=text],.yumefit-giftcard input[type=email],.yumefit-giftcard select,.yumefit-giftcard textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
            .yumefit-giftcard .gc-btn{background:#1c1f23;color:#fff;border:0;padding:12px 24px;border-radius:8px;font-weight:600;font-size:15px;cursor:pointer}
            .yumefit-giftcard .gc-msg{padding:12px 14px;border-radius:8px;margin:0 0 16px}
            .yumefit-giftcard .gc-msg.err{background:#fdecea;color:#a3261c}
            .yumefit-giftcard .gc-msg.ok{background:#e7f6ec;color:#1c6b39}
            .yumefit-giftcard .gc-check{display:flex;gap:8px;align-items:flex-start;font-weight:400}
        </style>
        <?php if ($error) : ?>
            <div class="gc-msg err"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        <?php if ($notice) : ?>
            <div class="gc-msg ok"><?php echo esc_html($notice); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(yumefit_giftcard_action_url('yumefit_giftcard_purchase')); ?>">
            <?php wp_nonce_field('yumefit_giftcard_purchase'); ?>

            <div class="gc-row">
                <label><?php esc_html_e('Vali teenus või pakett', 'latepoint-yumefit-giftcards'); ?></label>
                <?php if (!$items) : ?>
                    <p><?php esc_html_e('Hetkel pole ühtegi teenust ega paketti saadaval.', 'latepoint-yumefit-giftcards'); ?></p>
                <?php else : ?>
                    <select name="item" required>
                        <option value="" disabled selected><?php esc_html_e('Vali...', 'latepoint-yumefit-giftcards'); ?></option>
                        <?php if ($services) : ?>
                            <optgroup label="<?php esc_attr_e('Teenused', 'latepoint-yumefit-giftcards'); ?>">
                                <?php foreach ($services as $key => $item) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($item['name'] . ' — ' . $price_fmt($item['price'])); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($bundles) : ?>
                            <optgroup label="<?php esc_attr_e('Paketid', 'latepoint-yumefit-giftcards'); ?>">
                                <?php foreach ($bundles as $key => $item) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($item['name'] . ' — ' . $price_fmt($item['price'])); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="gc-row">
                <label><?php esc_html_e('Sinu nimi', 'latepoint-yumefit-giftcards'); ?></label>
                <input type="text" name="buyer_name" maxlength="180" required>
            </div>
            <div class="gc-row">
                <label><?php esc_html_e('Sinu e-post', 'latepoint-yumefit-giftcards'); ?></label>
                <input type="email" name="buyer_email" maxlength="180" required>
            </div>

            <div class="gc-row">
                <label><?php esc_html_e('Kingisaaja nimi', 'latepoint-yumefit-giftcards'); ?></label>
                <input type="text" name="recipient_name" maxlength="180">
            </div>
            <div class="gc-row">
                <label><?php esc_html_e('Kingisaaja e-post', 'latepoint-yumefit-giftcards'); ?></label>
                <input type="email" name="recipient_email" maxlength="180">
            </div>
            <div class="gc-row">
                <label><?php esc_html_e('Tervitus (valikuline)', 'latepoint-yumefit-giftcards'); ?></label>
                <textarea name="message" rows="3" maxlength="500"></textarea>
            </div>

            <div class="gc-row">
                <label class="gc-check">
                    <input type="checkbox" name="deliver_to_recipient" value="1">
                    <span><?php esc_html_e('See on kingitus — saada kood otse kingisaaja e-postile', 'latepoint-yumefit-giftcards'); ?></span>
                </label>
            </div>

            <button type="submit" class="gc-btn"><?php esc_html_e('Osta ja maksa', 'latepoint-yumefit-giftcards'); ?></button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/* ----------------------------------------------------------------------------
 * Purchase handler → start EveryPay payment
 * ------------------------------------------------------------------------- */
add_action('admin_post_nopriv_yumefit_giftcard_purchase', 'yumefit_giftcard_handle_purchase');
add_action('admin_post_yumefit_giftcard_purchase', 'yumefit_giftcard_handle_purchase');
function yumefit_giftcard_handle_purchase(): void {
    $back = wp_get_referer() ?: home_url();

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'yumefit_giftcard_purchase')) {
        yumefit_giftcard_redirect_error($back, __('Vorm aegus, palun proovi uuesti.', 'latepoint-yumefit-giftcards'));
    }
    if (!class_exists('OsEverypayApiHelper') || !class_exists('OsCouponModel') || !class_exists('OsMoneyHelper')) {
        yumefit_giftcard_redirect_error($back, __('Makselahendus pole hetkel saadaval.', 'latepoint-yumefit-giftcards'));
    }

    $item_key = sanitize_text_field(wp_unslash($_POST['item'] ?? ''));
    $item     = yumefit_giftcard_items()[$item_key] ?? null; // resolves price + validity server-side
    if (!$item) {
        yumefit_giftcard_redirect_error($back, __('Vali palun kehtiv teenus või pakett.', 'latepoint-yumefit-giftcards'));
    }
    $amount = round($item['price'], 2);

    $buyer_name      = sanitize_text_field(wp_unslash($_POST['buyer_name'] ?? ''));
    $buyer_email     = sanitize_email(wp_unslash($_POST['buyer_email'] ?? ''));
    $recipient_name  = sanitize_text_field(wp_unslash($_POST['recipient_name'] ?? ''));
    $recipient_email = sanitize_email(wp_unslash($_POST['recipient_email'] ?? ''));
    $message         = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $deliver         = !empty($_POST['deliver_to_recipient']) ? 1 : 0;

    if (!is_email($buyer_email)) {
        yumefit_giftcard_redirect_error($back, __('Palun sisesta korrektne e-posti aadress.', 'latepoint-yumefit-giftcards'));
    }
    if ($deliver && !is_email($recipient_email)) {
        yumefit_giftcard_redirect_error($back, __('Kingituse saatmiseks on vaja kingisaaja korrektset e-posti.', 'latepoint-yumefit-giftcards'));
    }

    global $wpdb;
    $order_reference = 'gc_' . wp_generate_password(24, false, false);
    $inserted        = $wpdb->insert(yumefit_giftcard_table(), [
        'order_reference'      => $order_reference,
        'status'               => 'pending',
        'amount'               => OsMoneyHelper::pad_to_db_format($amount),
        'item_type'            => $item['type'],
        'item_id'              => $item['id'],
        'item_name'            => $item['name'],
        'buyer_name'           => $buyer_name,
        'buyer_email'          => $buyer_email,
        'recipient_name'       => $recipient_name,
        'recipient_email'      => $recipient_email,
        'message'              => $message,
        'deliver_to_recipient' => $deliver,
        'created_at'           => current_time('mysql'),
    ]);
    if (!$inserted) {
        yumefit_giftcard_redirect_error($back, __('Tellimuse loomine ebaõnnestus, palun proovi uuesti.', 'latepoint-yumefit-giftcards'));
    }

    $payment = OsEverypayApiHelper::create_oneoff_payment([
        'amount'          => $amount,
        'order_reference' => $order_reference,
        'email'           => $buyer_email,
        'customer_ip'     => class_exists('OsUtilHelper') ? OsUtilHelper::get_user_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
        'locale'          => substr(get_locale(), 0, 2),
        'customer_url'    => add_query_arg(['ref' => $order_reference, 'back' => rawurlencode($back)], yumefit_giftcard_action_url('yumefit_giftcard_return')),
        'callback_url'    => yumefit_giftcard_action_url('yumefit_giftcard_callback'),
    ]);

    if (empty($payment['payment_link']) || empty($payment['payment_reference'])) {
        yumefit_giftcard_redirect_error($back, __('Makse alustamine ebaõnnestus, palun proovi uuesti.', 'latepoint-yumefit-giftcards'));
    }

    $wpdb->update(yumefit_giftcard_table(), ['payment_reference' => $payment['payment_reference']], ['order_reference' => $order_reference]);

    wp_redirect($payment['payment_link']);
    exit;
}

/* ----------------------------------------------------------------------------
 * EveryPay callback (server-to-server) and customer return — both issue (idempotent)
 * ------------------------------------------------------------------------- */
add_action('admin_post_nopriv_yumefit_giftcard_callback', 'yumefit_giftcard_handle_callback');
add_action('admin_post_yumefit_giftcard_callback', 'yumefit_giftcard_handle_callback');
function yumefit_giftcard_handle_callback(): void {
    $order_reference = sanitize_text_field(wp_unslash($_REQUEST['order_reference'] ?? ''));
    if ($order_reference) {
        yumefit_giftcard_try_issue($order_reference);
    }
    status_header(200);
    exit;
}

add_action('admin_post_nopriv_yumefit_giftcard_return', 'yumefit_giftcard_handle_return');
add_action('admin_post_yumefit_giftcard_return', 'yumefit_giftcard_handle_return');
function yumefit_giftcard_handle_return(): void {
    $order_reference = sanitize_text_field(wp_unslash($_REQUEST['ref'] ?? ''));
    $back            = isset($_REQUEST['back']) ? esc_url_raw(urldecode(wp_unslash($_REQUEST['back']))) : home_url();
    $issued          = $order_reference ? yumefit_giftcard_try_issue($order_reference) : false;

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    $title = $issued
        ? __('Aitäh! Kinkekaart on teel.', 'latepoint-yumefit-giftcards')
        : __('Makse ootel', 'latepoint-yumefit-giftcards');
    $desc = $issued
        ? __('Kinkekaardi kood saadeti e-postiga.', 'latepoint-yumefit-giftcards')
        : __('Kui makse õnnestus, saadame koodi e-postiga mõne hetke jooksul.', 'latepoint-yumefit-giftcards');
    ?>
<!DOCTYPE html><html lang="et"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($title); ?></title>
<style>body{font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f6f6f6;margin:0;color:#1c1f23}.w{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box}.b{background:#fff;border-radius:12px;padding:32px 28px;max-width:440px;width:100%;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.06)}h1{font-size:20px;margin:0 0 8px}p{color:#5a6068;margin:0 0 24px;font-size:14px}a{display:inline-block;background:#1c1f23;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600}</style>
</head><body><div class="w"><div class="b">
<h1><?php echo esc_html($title); ?></h1>
<p><?php echo esc_html($desc); ?></p>
<a href="<?php echo esc_url($back); ?>"><?php esc_html_e('Tagasi', 'latepoint-yumefit-giftcards'); ?></a>
</div></div></body></html>
    <?php
    exit;
}

/* ----------------------------------------------------------------------------
 * Verify payment, mint the coupon, email it. Idempotent and atomic.
 * ------------------------------------------------------------------------- */
function yumefit_giftcard_try_issue(string $order_reference): bool {
    global $wpdb;
    $table = yumefit_giftcard_table();

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_reference = %s", $order_reference));
    if (!$row) {
        return false;
    }
    if ($row->status === 'paid') {
        return true; // already issued
    }
    if (empty($row->payment_reference) || !class_exists('OsEverypayApiHelper')) {
        return false;
    }

    $payment = OsEverypayApiHelper::get_payment_status((string) $row->payment_reference);
    $state   = $payment['payment_state'] ?? '';
    if (!in_array($state, ['settled', 'authorized', 'authorised'], true)) {
        return false;
    }

    // Verify it's our account and the amount matches (anti-tamper).
    $expected = OsMoneyHelper::pad_to_db_format($row->amount);
    $returned = OsMoneyHelper::pad_to_db_format($payment['initial_amount'] ?? $payment['standing_amount'] ?? $payment['amount'] ?? 0);
    $account_ok = (string) ($payment['account_name'] ?? '') === OsEverypayApiHelper::get_account_name()
        && (string) ($payment['api_username'] ?? '') === OsEverypayApiHelper::get_api_username();
    if ($expected !== $returned || !$account_ok) {
        return false;
    }

    // Atomically claim this row so concurrent callback+return can't double-issue.
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET status = 'issuing' WHERE order_reference = %s AND status = 'pending'",
        $order_reference
    ));
    if (!$claimed) {
        return $row->status === 'paid';
    }

    $coupon      = yumefit_giftcard_create_coupon($row);
    $valid_until = $coupon ? $coupon->active_to : null;

    if (!$coupon) {
        $wpdb->update($table, ['status' => 'pending'], ['order_reference' => $order_reference]); // let it retry
        return false;
    }

    $wpdb->update($table, [
        'status'      => 'paid',
        'coupon_id'   => $coupon->id,
        'coupon_code' => $coupon->code,
        'valid_until' => $valid_until,
        'paid_at'     => current_time('mysql'),
    ], ['order_reference' => $order_reference]);

    yumefit_giftcard_send_emails($row, $coupon->code, $valid_until);
    return true;
}

function yumefit_giftcard_create_coupon($row) {
    // 100%-off, single-use, scoped to the exact service or package purchased.
    $rules = ['limit_total' => 1];
    if ($row->item_type === 'bundle') {
        $rules['bundle_ids'] = (string) $row->item_id;
    } else {
        $rules['service_ids'] = (string) $row->item_id;
    }

    $for_name = $row->recipient_name ?: $row->buyer_name;

    $coupon                 = new OsCouponModel();
    $coupon->code           = yumefit_giftcard_unique_code();
    $coupon->name           = trim(sprintf(__('Kinkekaart: %s — %s', 'latepoint-yumefit-giftcards'), $row->item_name, $for_name));
    $coupon->discount_type  = 'percent';
    $coupon->discount_value = '100';
    $coupon->rules          = wp_json_encode($rules);
    $coupon->status         = defined('LATEPOINT_COUPON_STATUS_ACTIVE') ? LATEPOINT_COUPON_STATUS_ACTIVE : 'active';
    $coupon->active_from    = date('Y-m-d');
    $coupon->active_to      = date('Y-m-d', strtotime('+' . YUMEFIT_GIFTCARD_VALID_MONTHS . ' months'));

    return $coupon->save() ? $coupon : null;
}

function yumefit_giftcard_unique_code(): string {
    global $wpdb;
    $table = defined('LATEPOINT_TABLE_COUPONS') ? LATEPOINT_TABLE_COUPONS : $wpdb->prefix . 'latepoint_coupons';
    do {
        $code   = 'GIFT-' . strtoupper(wp_generate_password(8, false, false));
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE code = %s", $code));
    } while ($exists);
    return $code;
}

function yumefit_giftcard_send_emails($row, string $code, ?string $valid_until): void {
    $valid_label = $valid_until ? date_i18n(get_option('date_format'), strtotime($valid_until)) : '';
    $site        = get_bloginfo('name');

    $lines = [
        sprintf(__('Kinkekaart: %s', 'latepoint-yumefit-giftcards'), $row->item_name),
        sprintf(__('Kood: %s', 'latepoint-yumefit-giftcards'), $code),
    ];
    if ($valid_label) {
        $lines[] = sprintf(__('Kehtib kuni: %s', 'latepoint-yumefit-giftcards'), $valid_label);
    }
    $lines[] = '';
    $lines[] = __('Sisesta kood broneeringu vormistamisel valitud teenuse/paketi juures.', 'latepoint-yumefit-giftcards');

    // To recipient (if buyer chose to deliver directly)
    if ($row->deliver_to_recipient && is_email($row->recipient_email)) {
        $body = '';
        if ($row->buyer_name) {
            $body .= sprintf(__('%s on saatnud sulle kinkekaardi!', 'latepoint-yumefit-giftcards'), $row->buyer_name) . "\n\n";
        }
        if ($row->message) {
            $body .= '"' . $row->message . "\"\n\n";
        }
        $body .= implode("\n", $lines);
        wp_mail($row->recipient_email, sprintf(__('Sinu kinkekaart — %s', 'latepoint-yumefit-giftcards'), $site), $body);
    }

    // Always send a copy/receipt to the buyer
    if (is_email($row->buyer_email)) {
        $body  = __('Aitäh ostu eest! Sinu kinkekaart on valmis.', 'latepoint-yumefit-giftcards') . "\n\n";
        $body .= implode("\n", $lines);
        if ($row->deliver_to_recipient && $row->recipient_email) {
            $body .= "\n\n" . sprintf(__('Kood saadeti ka kingisaajale: %s', 'latepoint-yumefit-giftcards'), $row->recipient_email);
        }
        wp_mail($row->buyer_email, sprintf(__('Kinkekaardi kood — %s', 'latepoint-yumefit-giftcards'), $site), $body);
    }
}

function yumefit_giftcard_redirect_error(string $back, string $message): void {
    wp_redirect(add_query_arg('gc_error', rawurlencode($message), $back));
    exit;
}

/* ----------------------------------------------------------------------------
 * Minimal admin list: Tools → Gift Cards
 * ------------------------------------------------------------------------- */
add_action('admin_menu', 'yumefit_giftcard_admin_menu');
function yumefit_giftcard_admin_menu(): void {
    add_management_page(
        __('Gift Cards', 'latepoint-yumefit-giftcards'),
        __('Gift Cards', 'latepoint-yumefit-giftcards'),
        'manage_options',
        'yumefit-giftcards',
        'yumefit_giftcard_admin_page'
    );
}

function yumefit_giftcard_admin_page(): void {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM " . yumefit_giftcard_table() . " ORDER BY id DESC LIMIT 200");
    echo '<div class="wrap"><h1>' . esc_html__('Gift Cards', 'latepoint-yumefit-giftcards') . '</h1>';
    echo '<table class="widefat striped"><thead><tr>';
    foreach (['#', 'Status', 'Gift', 'Paid', 'Code', 'Buyer', 'Recipient', 'Valid until', 'Created'] as $h) {
        echo '<th>' . esc_html($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="9">' . esc_html__('No gift cards yet.', 'latepoint-yumefit-giftcards') . '</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . (int) $r->id . '</td>';
        echo '<td>' . esc_html($r->status) . '</td>';
        echo '<td>' . esc_html($r->item_name ?: '—') . '</td>';
        echo '<td>' . esc_html(yumefit_giftcard_amount_label($r->amount)) . ' €</td>';
        echo '<td><code>' . esc_html($r->coupon_code ?: '—') . '</code></td>';
        echo '<td>' . esc_html(trim($r->buyer_name . ' ' . $r->buyer_email)) . '</td>';
        echo '<td>' . esc_html(trim($r->recipient_name . ' ' . $r->recipient_email)) . '</td>';
        echo '<td>' . esc_html($r->valid_until ?: '—') . '</td>';
        echo '<td>' . esc_html($r->created_at) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
