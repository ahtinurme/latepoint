<?php
/**
 * @var string   $slots_raw
 * @var string[] $bad       invalid lines in the saved list
 * @var bool     $saved
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div style="padding: 20px; max-width: 640px;">
    <?php if ($saved) { ?>
        <div style="background: #e7f7ed; border: 1px solid #b6e4c6; color: #1d7a45; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">Salvestatud.</div>
    <?php } ?>
    <?php if ($bad) { ?>
        <div style="background: #fdeaea; border: 1px solid #f0b8b8; color: #b02a2a; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
            Vigased read (õige kuju on nt "07.07.2026 19:00"): <?php echo esc_html(implode(' | ', $bad)); ?>
        </div>
    <?php } ?>

    <p>
        Üks treening rea kohta, kujul <code>07.07.2026 19:00</code> (kuupäev + algusaeg).
        Treeningu pikkus tuleb teenuse seadetest. Jooga rühmatreening on broneeritav
        <strong>ainult</strong> siin loetletud aegadel.
    </p>
    <p>
        Teised teenused sel kellaajal automaatselt ei sulgu — tund on kaitstud alles siis,
        kui keegi on treeningusse broneerinud, või kui lõpetad Marleeni tööpäeva LatePointis
        enne treeningu algust.
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="latepoint_route_call">
        <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('jooga', 'save')); ?>">
        <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('save_jooga_slots')); ?>">

        <textarea name="params[slots]" rows="14" style="width: 240px; font-family: monospace;"><?php echo esc_textarea($slots_raw); ?></textarea>

        <div style="margin-top: 12px;">
            <button type="submit" class="latepoint-btn latepoint-btn-primary">Salvesta</button>
        </div>
    </form>
</div>
