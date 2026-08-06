<?php
/**
 * @var array<string, array<string, array{ga: int, ta: int, any: int}>> $days
 *     date => size => demand split (ga = needs plates, ta = needs no plates, any = no preference)
 * @var array<string, array{ga: int, ta: int}> $stock costumes owned per size and plates variant
 * @var array<string, int> $day_totals date => total EMS bookings that day
 * @var bool $saved
 * @var bool $field_missing
 * @var string $from
 * @var string $prev_from
 * @var string $next_from
 * @var string $range_label
 * @var bool $is_today
 */
if (!defined('ABSPATH')) {
    exit;
}
$sizes = YUMEFIT_COSTUME_SIZES;
?>
<div style="padding: 20px; max-width: 860px;">
    <?php if ($saved) { ?>
        <div style="background: #e7f7ed; border: 1px solid #b6e4c6; color: #1d7a45; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">Salvestatud.</div>
    <?php } ?>
    <?php if ($field_missing) { ?>
        <div style="background: #fdeaea; border: 1px solid #f0b8b8; color: #b02a2a; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
            Kostüümi suuruse väli on seadistamata — käivita serveris <code>scripts/setup_costume_field.php</code>.
        </div>
    <?php } ?>

    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <a href="<?php echo esc_url(OsRouterHelper::build_link(['costumes', 'index'], ['from' => $prev_from])); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-left"></i></a>
        <strong style="min-width: 120px; text-align: center;"><?php echo esc_html($range_label); ?></strong>
        <a href="<?php echo esc_url(OsRouterHelper::build_link(['costumes', 'index'], ['from' => $next_from])); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-right"></i></a>
        <?php if (!$is_today) { ?>
            <a href="<?php echo esc_url(OsRouterHelper::build_link(['costumes', 'index'])); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey">Täna</a>
        <?php } ?>
    </div>

    <p>
        EMS-treeningute broneeringud 7 päeva kohta, kliendi kostüümi suuruse järgi.
        Sisesta ülemistele ridadele, mitu kostüümi meil on (plaatidega / plaatideta eraldi).
        <span style="background:#fdeaea;padding:0 6px;border-radius:4px;">Punane</span> = broneeringuid on
        rohkem kui kostüüme (kokku või plaadisoovi järgi) — pane kostüümid õigeks ajaks pesu.
        Vahesuurusega klient (nt XS/S) loetakse mõlemas veerus, seega pigem näitab tabel puudust varem.
        Veerg <strong>?</strong> = kliendil on suurus määramata (määra see kliendikaardil).
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="latepoint_route_call">
        <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('costumes', 'save')); ?>">
        <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('save_costume_stock')); ?>">
        <input type="hidden" name="params[from]" value="<?php echo esc_attr($from); ?>">

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr style="border-bottom: 2px solid #ddd; text-align: center;">
                <th style="text-align: left; padding: 6px 8px;">Päev</th>
                <?php foreach ($sizes as $size) { ?>
                    <th style="padding: 6px 8px;"><?php echo esc_html($size); ?></th>
                <?php } ?>
                <th style="padding: 6px 8px; color: #b07d1a;">?</th>
                <th style="padding: 6px 8px;">Kokku</th>
            </tr>
            <?php foreach (['ga' => 'Plaatidega kostüüme', 'ta' => 'Plaatideta kostüüme'] as $variant => $label) { ?>
                <tr style="border-bottom: <?php echo $variant === 'ta' ? '2px solid #ddd' : '1px solid #eee'; ?>; text-align: center; color: #6b6b6b;">
                    <td style="text-align: left; padding: 6px 8px;"><?php echo esc_html($label); ?></td>
                    <?php foreach ($sizes as $size) { ?>
                        <td style="padding: 4px;">
                            <input type="number" min="0" name="params[stock][<?php echo esc_attr($size); ?>][<?php echo esc_attr($variant); ?>]"
                                   value="<?php echo (int) $stock[$size][$variant]; ?>" style="width: 52px; text-align: center;">
                        </td>
                    <?php } ?>
                    <td></td>
                    <td style="padding: 6px 8px; font-weight: 700;"><?php echo array_sum(array_column($stock, $variant)); ?></td>
                </tr>
            <?php } ?>
            <?php foreach ($days as $date => $counts) { ?>
                <tr style="border-bottom: 1px solid #eee; text-align: center;">
                    <td style="text-align: left; padding: 6px 8px; white-space: nowrap;">
                        <?php echo esc_html(date_i18n('D, d.m', strtotime($date))); ?>
                    </td>
                    <?php foreach ($sizes as $size) {
                        $c = $counts[$size];
                        $st = $stock[$size];
                        $total = $c['ga'] + $c['ta'] + $c['any'];
                        $stock_total = $st['ga'] + $st['ta'];
                        $over = $total > $stock_total || $c['ga'] > $st['ga'] || $c['ta'] > $st['ta']; ?>
                        <td style="padding: 6px 8px; vertical-align: top; <?php echo $over && $total ? 'background:#fdeaea;color:#b02a2a;font-weight:700;' : ($total ? '' : 'color:#c5c5c5;'); ?>">
                            <?php echo $total; ?><?php if ($over && $total) { ?> / <?php echo $stock_total; } ?>
                            <?php if ($c['ga'] || $c['ta']) { ?>
                                <div style="font-size: 11px; font-weight: 400; color: <?php echo $over ? '#b02a2a' : '#6b6b6b'; ?>; white-space: nowrap;">
                                    <?php echo $c['ga'] ? $c['ga'] . ' plaatidega' : ''; ?><?php echo $c['ga'] && $c['ta'] ? '<br>' : ''; ?><?php echo $c['ta'] ? $c['ta'] . ' plaatideta' : ''; ?>
                                </div>
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <?php $unknown = array_sum($counts['?']); ?>
                    <td style="padding: 6px 8px; vertical-align: top; <?php echo $unknown ? 'background:#fdf3e0;color:#b07d1a;font-weight:700;' : 'color:#c5c5c5;'; ?>">
                        <?php echo $unknown; ?>
                    </td>
                    <td style="padding: 6px 8px; vertical-align: top; font-weight: 700; <?php echo $day_totals[$date] ? '' : 'color:#c5c5c5;'; ?>">
                        <?php echo $day_totals[$date]; ?>
                    </td>
                </tr>
            <?php } ?>
        </table>

        <button type="submit" class="latepoint-btn latepoint-btn-primary">Salvesta</button>
    </form>
</div>
