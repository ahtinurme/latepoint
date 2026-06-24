<?php
/**
 * @var OsAgentModel[] $agents
 * @var int[]          $weekdays
 * @var array          $grid
 * @var bool           $saved
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
    .av-wrap { padding: 20px; }
    .av-table { border-collapse: collapse; width: 100%; background: #fff; }
    .av-table th, .av-table td { border: 1px solid #e6e9f0; padding: 8px 10px; text-align: center; vertical-align: middle; }
    .av-table thead th { background: #f7f8fc; font-weight: 600; }
    .av-table td.av-agent, .av-table th.av-agent { text-align: left; white-space: nowrap; font-weight: 600; position: sticky; left: 0; background: #fff; z-index: 1; }
    .av-cell { display: flex; align-items: center; justify-content: center; gap: 4px; }
    .av-cell input[type="time"] { width: 92px; border: 1px solid #d5d9e3; border-radius: 6px; padding: 4px 6px; }
    .av-cell .av-dash { color: #9aa1b2; }
    .av-cell.av-inherited input { color: #9aa1b2; border-style: dashed; }
    .av-locked { color: #6b7280; font-size: 12px; line-height: 1.5; }
    .av-locked a { display: block; font-size: 11px; }
    .av-actions { position: sticky; bottom: 0; background: #fff; padding: 16px 0; margin-top: 16px; border-top: 1px solid #e6e9f0; }
    .av-hint { color: #6b7280; margin: 0 0 16px; }
    .av-notice { background: #e7f7ed; border: 1px solid #b6e4c6; color: #1d7a45; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
</style>

<div class="av-wrap">
    <?php if ($saved) { ?>
        <div class="av-notice"><?php esc_html_e('Availability saved.', 'latepoint'); ?></div>
    <?php } ?>

    <p class="av-hint">
        <?php esc_html_e('Set each agent\'s weekly working hours. Leave a day empty for a day off. Dashed values are inherited from the default schedule and become the agent\'s own once saved.', 'latepoint'); ?>
    </p>

    <?php if (empty($agents)) { ?>
        <p><?php esc_html_e('No active agents found.', 'latepoint'); ?></p>
    <?php } else { ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="latepoint_route_call">
            <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('availability', 'save')); ?>">
            <?php wp_nonce_field('save_availability'); ?>

            <div style="overflow-x:auto;">
                <table class="av-table">
                    <thead>
                        <tr>
                            <th class="av-agent"><?php esc_html_e('Agent', 'latepoint'); ?></th>
                            <?php foreach ($weekdays as $day) { ?>
                                <th><?php echo esc_html(OsBookingHelper::get_weekday_name_by_number($day, true)); ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent) { ?>
                            <tr>
                                <td class="av-agent"><?php echo esc_html($agent->full_name); ?></td>
                                <?php foreach ($weekdays as $day) {
                                    $cell = $grid[$agent->id][$day]; ?>
                                    <td>
                                        <?php if ($cell['state'] === 'locked') { ?>
                                            <div class="av-locked">
                                                <?php foreach ($cell['periods'] as $p) {
                                                    echo esc_html($p) . '<br>';
                                                } ?>
                                                <a href="<?php echo esc_url(OsRouterHelper::build_link(['agents', 'edit'], ['id' => $agent->id])); ?>"><?php esc_html_e('edit on agent page', 'latepoint'); ?></a>
                                            </div>
                                        <?php } else { ?>
                                            <div class="av-cell <?php echo $cell['inherited'] ? 'av-inherited' : ''; ?>">
                                                <input type="time" name="availability[<?php echo (int) $agent->id; ?>][<?php echo (int) $day; ?>][start]" value="<?php echo esc_attr($cell['start']); ?>">
                                                <span class="av-dash">–</span>
                                                <input type="time" name="availability[<?php echo (int) $agent->id; ?>][<?php echo (int) $day; ?>][end]" value="<?php echo esc_attr($cell['end']); ?>">
                                            </div>
                                        <?php } ?>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="av-actions">
                <button type="submit" class="latepoint-btn latepoint-btn-primary"><?php esc_html_e('Save Availability', 'latepoint'); ?></button>
            </div>
        </form>
    <?php } ?>
</div>
