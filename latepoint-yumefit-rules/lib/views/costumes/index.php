<?php
/**
 * @var array<string, array<string, int>> $days  date => [size => booked count], includes '?' (size not set)
 * @var array<string, int>                $stock size => costumes owned
 * @var bool                              $saved
 * @var bool                              $field_missing
 */
if (!defined('ABSPATH')) {
    exit;
}
$sizes = YUMEFIT_COSTUME_SIZES;
?>
<div style="padding: 20px; max-width: 760px;">
    <?php if ($saved) { ?>
        <div style="background: #e7f7ed; border: 1px solid #b6e4c6; color: #1d7a45; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">Salvestatud.</div>
    <?php } ?>
    <?php if ($field_missing) { ?>
        <div style="background: #fdeaea; border: 1px solid #f0b8b8; color: #b02a2a; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
            Kostüümi suuruse väli on seadistamata — käivita serveris <code>scripts/setup_costume_field.php</code>.
        </div>
    <?php } ?>

    <p>
        EMS-treeningute broneeringud järgmise 7 päeva kohta, kliendi kostüümi suuruse järgi.
        <span style="background:#fdeaea;padding:0 6px;border-radius:4px;">Punane</span> = broneeringuid on
        rohkem kui kostüüme — pane kostüümid õigeks ajaks pesu.
        Vahesuurusega klient (nt XS/S) loetakse mõlemas veerus, seega pigem näitab tabel puudust varem.
        Veerg <strong>?</strong> = kliendil on suurus määramata (määra see kliendikaardil).
    </p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr style="border-bottom: 2px solid #ddd; text-align: center;">
            <th style="text-align: left; padding: 6px 8px;">Päev</th>
            <?php foreach ($sizes as $size) { ?>
                <th style="padding: 6px 8px;"><?php echo esc_html($size); ?></th>
            <?php } ?>
            <th style="padding: 6px 8px; color: #b07d1a;">?</th>
        </tr>
        <tr style="border-bottom: 1px solid #ddd; text-align: center; color: #6b6b6b;">
            <td style="text-align: left; padding: 6px 8px;">Kostüüme olemas</td>
            <?php foreach ($sizes as $size) { ?>
                <td style="padding: 6px 8px;"><?php echo (int) $stock[$size]; ?></td>
            <?php } ?>
            <td></td>
        </tr>
        <?php foreach ($days as $date => $counts) { ?>
            <tr style="border-bottom: 1px solid #eee; text-align: center;">
                <td style="text-align: left; padding: 6px 8px; white-space: nowrap;">
                    <?php echo esc_html(date_i18n('D, d.m', strtotime($date))); ?>
                </td>
                <?php foreach ($sizes as $size) {
                    $over = $counts[$size] > $stock[$size]; ?>
                    <td style="padding: 6px 8px; <?php echo $over ? 'background:#fdeaea;color:#b02a2a;font-weight:700;' : ($counts[$size] ? '' : 'color:#c5c5c5;'); ?>">
                        <?php echo (int) $counts[$size]; ?><?php if ($over) { ?> / <?php echo (int) $stock[$size]; } ?>
                    </td>
                <?php } ?>
                <td style="padding: 6px 8px; <?php echo $counts['?'] ? 'background:#fdf3e0;color:#b07d1a;font-weight:700;' : 'color:#c5c5c5;'; ?>">
                    <?php echo (int) $counts['?']; ?>
                </td>
            </tr>
        <?php } ?>
    </table>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="latepoint_route_call">
        <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('costumes', 'save')); ?>">
        <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('save_costume_stock')); ?>">

        <h3 style="margin-bottom: 8px;">Mitu kostüümi meil on</h3>
        <div style="display: flex; gap: 14px; align-items: flex-end;">
            <?php foreach ($sizes as $size) { ?>
                <label style="display: flex; flex-direction: column; gap: 4px; font-weight: 600;">
                    <?php echo esc_html($size); ?>
                    <input type="number" min="0" name="params[stock][<?php echo esc_attr($size); ?>]"
                           value="<?php echo (int) $stock[$size]; ?>" style="width: 70px;">
                </label>
            <?php } ?>
            <button type="submit" class="latepoint-btn latepoint-btn-primary">Salvesta</button>
        </div>
    </form>
</div>
