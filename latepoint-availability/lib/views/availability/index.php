<?php
/**
 * @var OsAgentModel[] $agents
 * @var array          $dates       list of ['date','week_day','day_name','day_number']
 * @var string         $week_start
 * @var string         $prev_week
 * @var string         $next_week
 * @var array          $grid
 * @var bool           $saved
 */
if (!defined('ABSPATH')) {
    exit;
}
$prev_link = OsRouterHelper::build_link(['availability', 'index'], ['week_start' => $prev_week]);
$next_link = OsRouterHelper::build_link(['availability', 'index'], ['week_start' => $next_week]);
?>
<style>
    .av-wrap { padding: 20px; }
    .av-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .av-toolbar .av-range { font-weight: 600; font-size: 15px; }
    .av-table { border-collapse: collapse; width: 100%; background: #fff; }
    .av-table th, .av-table td { border: 1px solid #e6e9f0; padding: 8px 10px; text-align: center; vertical-align: middle; }
    .av-table thead th { background: #f7f8fc; font-weight: 600; }
    .av-table thead th small { display: block; font-weight: 400; color: #6b7280; }
    .av-table thead th.av-today { background: #eef3ff; }
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
        <div class="av-notice"><?php esc_html_e('Availability saved.', 'latepoint-availability'); ?></div>
    <?php } ?>

    <div class="av-toolbar">
        <a href="<?php echo esc_url($prev_link); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-left"></i></a>
        <span class="av-range"><?php echo esc_html($dates[0]['day_number'] . ' – ' . $dates[6]['day_number']); ?></span>
        <a href="<?php echo esc_url($next_link); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-right"></i></a>
    </div>

    <p class="av-hint">
        <?php esc_html_e('Set each agent\'s working hours for these specific dates. Leave a day empty for a day off. Dashed values follow the recurring weekly schedule; edit them to create a date-specific override.', 'latepoint-availability'); ?>
    </p>

    <?php
    $today = OsTimeHelper::today_date();
    if (empty($agents)) { ?>
        <p><?php esc_html_e('No active agents found.', 'latepoint-availability'); ?></p>
    <?php } else { ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="latepoint_route_call">
            <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('availability', 'save')); ?>">
            <input type="hidden" name="params[week_start]" value="<?php echo esc_attr($week_start); ?>">
            <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('save_availability')); ?>">

            <div style="overflow-x:auto;">
                <table class="av-table">
                    <thead>
                        <tr>
                            <th class="av-agent"><?php esc_html_e('Agent', 'latepoint-availability'); ?></th>
                            <?php foreach ($dates as $d) { ?>
                                <th class="<?php echo $d['date'] === $today ? 'av-today' : ''; ?>">
                                    <?php echo esc_html($d['day_name']); ?>
                                    <small><?php echo esc_html($d['day_number']); ?></small>
                                </th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent) { ?>
                            <tr>
                                <td class="av-agent"><?php echo esc_html($agent->full_name); ?></td>
                                <?php foreach ($dates as $d) {
                                    $cell = $grid[$agent->id][$d['date']]; ?>
                                    <td>
                                        <?php if ($cell['state'] === 'locked') { ?>
                                            <div class="av-locked">
                                                <?php foreach ($cell['periods'] as $p) {
                                                    echo esc_html($p) . '<br>';
                                                } ?>
                                                <a href="<?php echo esc_url(OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id])); ?>"><?php esc_html_e('edit on agent page', 'latepoint-availability'); ?></a>
                                            </div>
                                        <?php } else { ?>
                                            <div class="av-cell <?php echo $cell['inherited'] ? 'av-inherited' : ''; ?>">
                                                <input type="time" name="params[availability][<?php echo (int) $agent->id; ?>][<?php echo esc_attr($d['date']); ?>][start]" value="<?php echo esc_attr($cell['start']); ?>">
                                                <span class="av-dash">–</span>
                                                <input type="time" name="params[availability][<?php echo (int) $agent->id; ?>][<?php echo esc_attr($d['date']); ?>][end]" value="<?php echo esc_attr($cell['end']); ?>">
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
                <button type="submit" class="latepoint-btn latepoint-btn-primary"><?php esc_html_e('Save Availability', 'latepoint-availability'); ?></button>
            </div>
        </form>
    <?php } ?>
</div>

<script>
    // ponytail: seed an empty time field to the current hour at :00 so the picker
    // starts on a whole hour; any minute (e.g. 14:36) is still selectable after.
    document.querySelectorAll('.av-cell input[type="time"]').forEach(function (input) {
        input.addEventListener('focus', function () {
            if (!input.value) {
                input.value = String(new Date().getHours()).padStart(2, '0') + ':00';
            }
        });
    });
</script>
