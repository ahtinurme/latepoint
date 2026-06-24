<?php
/**
 * @var OsAgentModel[] $agents
 * @var array          $dates       list of ['date','week_day','day_name','day_number']
 * @var string         $week_start
 * @var string         $prev_week
 * @var string         $next_week
 * @var array          $grid        [agent_id][date] => ['inherited'=>bool, 'periods'=>[['start','end'],...]]
 * @var bool           $saved
 */
if (!defined('ABSPATH')) {
    exit;
}
$prev_link = OsRouterHelper::build_link(['availability', 'index'], ['week_start' => $prev_week]);
$next_link = OsRouterHelper::build_link(['availability', 'index'], ['week_start' => $next_week]);

$render_period = function (int $agent_id, string $date, int $index, string $start, string $end): string {
    $name = "params[availability][{$agent_id}][{$date}][periods][{$index}]";
    ob_start(); ?>
    <div class="av-period">
        <input type="time" name="<?php echo esc_attr($name); ?>[start]" value="<?php echo esc_attr($start); ?>">
        <span class="av-dash">–</span>
        <input type="time" name="<?php echo esc_attr($name); ?>[end]" value="<?php echo esc_attr($end); ?>">
        <button type="button" class="av-remove" title="<?php esc_attr_e('Remove', 'latepoint-availability'); ?>">×</button>
    </div>
    <?php
    return ob_get_clean();
};
?>
<style>
    .av-wrap { padding: 20px; }
    .av-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .av-toolbar .av-range { font-weight: 600; font-size: 15px; }
    .av-table { border-collapse: collapse; width: 100%; background: #fff; }
    .av-table th, .av-table td { border: 1px solid #e6e9f0; padding: 6px 8px; text-align: center; vertical-align: top; }
    .av-table thead th { background: #f7f8fc; font-weight: 600; vertical-align: middle; }
    .av-table thead th small { display: block; font-weight: 400; color: #6b7280; }
    .av-table thead th.av-today { background: #eef3ff; }
    .av-table td.av-agent, .av-table th.av-agent { text-align: left; white-space: nowrap; font-weight: 600; position: sticky; left: 0; background: #fff; z-index: 1; vertical-align: middle; }
    .av-stack { display: flex; flex-direction: column; gap: 4px; align-items: center; }
    .av-period { display: flex; align-items: center; gap: 4px; }
    .av-period input[type="time"] { width: 84px; border: 1px solid #d5d9e3; border-radius: 6px; padding: 4px 6px; }
    .av-period .av-dash { color: #9aa1b2; }
    .av-inherited .av-period input { color: #9aa1b2; border-style: dashed; }
    .av-remove { border: none; background: none; color: #c0394b; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 2px; }
    .av-add { display: inline-block; font-size: 12px; cursor: pointer; color: #2b6cb0; text-decoration: none; }
    .av-add:hover { text-decoration: underline; }
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
        <?php esc_html_e('Set each agent\'s working hours for these specific dates. Add multiple periods for split shifts; remove them all for a day off. Dashed values follow the recurring weekly schedule; edit them to create a date-specific override.', 'latepoint-availability'); ?>
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
                                        <div class="av-stack <?php echo $cell['inherited'] ? 'av-inherited' : ''; ?>" data-aid="<?php echo (int) $agent->id; ?>" data-date="<?php echo esc_attr($d['date']); ?>">
                                            <input type="hidden" name="params[availability][<?php echo (int) $agent->id; ?>][<?php echo esc_attr($d['date']); ?>][present]" value="1">
                                            <?php foreach ($cell['periods'] as $i => $p) {
                                                echo $render_period((int) $agent->id, $d['date'], $i, $p['start'], $p['end']);
                                            } ?>
                                            <a class="av-add" href="#"><?php esc_html_e('+ add', 'latepoint-availability'); ?></a>
                                        </div>
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
    (function () {
        var newIndex = 100000;

        function blankPeriod(aid, date) {
            var name = 'params[availability][' + aid + '][' + date + '][periods][' + (newIndex++) + ']';
            var row = document.createElement('div');
            row.className = 'av-period';
            row.innerHTML =
                '<input type="time" name="' + name + '[start]">' +
                '<span class="av-dash">–</span>' +
                '<input type="time" name="' + name + '[end]">' +
                '<button type="button" class="av-remove" title="Remove">×</button>';
            return row;
        }

        document.addEventListener('click', function (e) {
            var add = e.target.closest('.av-add');
            if (add) {
                e.preventDefault();
                var stack = add.closest('.av-stack');
                stack.classList.remove('av-inherited');
                stack.insertBefore(blankPeriod(stack.dataset.aid, stack.dataset.date), add);
                return;
            }
            var remove = e.target.closest('.av-remove');
            if (remove) {
                e.preventDefault();
                remove.closest('.av-stack').classList.remove('av-inherited');
                remove.closest('.av-period').remove();
            }
        });

        // ponytail: seed an empty time field to the current hour at :00 so the picker
        // starts on a whole hour; any minute (e.g. 14:36) is still selectable after.
        document.addEventListener('focusin', function (e) {
            var input = e.target;
            if (input.matches('.av-period input[type="time"]') && !input.value) {
                input.value = String(new Date().getHours()).padStart(2, '0') + ':00';
            }
        });
    })();
</script>
