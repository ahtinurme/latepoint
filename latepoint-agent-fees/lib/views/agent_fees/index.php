<?php
/**
 * @var OsAgentModel[] $agents
 * @var array          $fees        ['schedule' => float, 'training' => float]
 * @var array          $stats       [agent_id => ['days','schedules','trainings','bookings']]
 * @var string         $month       YYYY-MM
 * @var string         $month_label
 * @var string         $prev_month
 * @var string         $next_month
 * @var bool           $saved
 */
if (!defined('ABSPATH')) {
    exit;
}
$prev_link = OsRouterHelper::build_link(['agent_fees', 'index'], ['month' => $prev_month]);
$next_link = OsRouterHelper::build_link(['agent_fees', 'index'], ['month' => $next_month]);

$shifts       = LATEPOINT_AGENT_FEES_SHIFTS;
$m2hm         = fn(int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
$shift_labels = array_map(fn($s) => $m2hm($s[0]) . '–' . $m2hm($s[1]), $shifts);
$money        = fn(float $v) => number_format($v, 2, '.', ' ') . ' €';
$units        = fn(float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
?>
<style>
    .af-wrap { padding: 20px; }
    .af-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .af-toolbar .af-range { font-weight: 600; font-size: 15px; min-width: 130px; text-align: center; }
    .af-table { border-collapse: collapse; width: 100%; background: #fff; margin-bottom: 24px; }
    .af-table th, .af-table td { border: 1px solid #e6e9f0; padding: 6px 10px; text-align: left; vertical-align: top; }
    .af-table thead th { background: #f7f8fc; font-weight: 600; }
    .af-table td.af-num, .af-table th.af-num { text-align: right; white-space: nowrap; }
    .af-table input[type="number"] { width: 80px; border: 1px solid #d5d9e3; border-radius: 6px; padding: 4px 6px; }
    .af-total { font-weight: 700; }
    .af-muted { color: #9aa1b2; }
    .af-hit { color: #1d7a45; font-weight: 600; }
    .af-combined { color: #b45309; font-weight: 600; }
    .af-notice { background: #e7f7ed; border: 1px solid #b6e4c6; color: #1d7a45; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
    .af-hint { color: #6b7280; margin: 0 0 16px; }
    details.af-agent { background: #fff; border: 1px solid #e6e9f0; border-radius: 8px; margin-bottom: 12px; }
    details.af-agent > summary { cursor: pointer; padding: 12px 16px; font-weight: 600; }
    details.af-agent > div { padding: 0 16px 16px; }
    details.af-agent .af-table { margin-bottom: 0; }
</style>

<div class="af-wrap">
    <?php if ($saved) { ?>
        <div class="af-notice"><?php esc_html_e('Fees saved.', 'latepoint-agent-fees'); ?></div>
    <?php } ?>

    <div class="af-toolbar">
        <a href="<?php echo esc_url($prev_link); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-left"></i></a>
        <span class="af-range"><?php echo esc_html($month_label); ?></span>
        <a href="<?php echo esc_url($next_link); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-right"></i></a>
    </div>

    <p class="af-hint">
        <?php printf(
            /* translators: 1: first shift, 2: second shift */
            esc_html__('A shift (%1$s or %2$s) covered at least 80%% by working hours earns the full schedule fee; below 80%% it earns a proportional part of the fee (covered hours ÷ shift hours). A shorter day totalling at least 4 hours inside 09:00–20:00 still earns one full schedule (combined). Trainings count happened and no-show bookings; cancelled are excluded. Bookings at the same time are one training, except jooga rühmatreening, which pays the group fee per participant.', 'latepoint-agent-fees'),
            esc_html($shift_labels[0]),
            esc_html($shift_labels[1])
        ); ?>
    </p>

    <?php if (empty($agents)) { ?>
        <p><?php esc_html_e('No active agents found.', 'latepoint-agent-fees'); ?></p>
        </div>
        <?php return;
    } ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="latepoint_route_call">
        <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('agent_fees', 'save_fees')); ?>">
        <input type="hidden" name="params[month]" value="<?php echo esc_attr($month); ?>">
        <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('save_agent_fees')); ?>">

        <?php
        $schedule_fee = (float) ($fees['schedule'] ?? 0);
        $training_fee = (float) ($fees['training'] ?? 0);
        $group_fee    = (float) ($fees['group'] ?? 0); ?>
        <p>
            <label>
                <?php esc_html_e('Schedule fee', 'latepoint-agent-fees'); ?>
                <input type="number" step="0.01" min="0" name="params[fees][schedule]" value="<?php echo esc_attr($schedule_fee ?: ''); ?>" style="width: 80px;"> €
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Training fee', 'latepoint-agent-fees'); ?>
                <input type="number" step="0.01" min="0" name="params[fees][training]" value="<?php echo esc_attr($training_fee ?: ''); ?>" style="width: 80px;"> €
            </label>
            &nbsp;&nbsp;
            <label>
                <?php esc_html_e('Group fee (per participant)', 'latepoint-agent-fees'); ?>
                <input type="number" step="0.01" min="0" name="params[fees][group]" value="<?php echo esc_attr($group_fee ?: ''); ?>" style="width: 80px;"> €
            </label>
            &nbsp;&nbsp;
            <button type="submit" class="latepoint-btn latepoint-btn-primary"><?php esc_html_e('Save Fees', 'latepoint-agent-fees'); ?></button>
        </p>

        <table class="af-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Agent', 'latepoint-agent-fees'); ?></th>
                    <?php foreach ($shift_labels as $label) { ?>
                        <th class="af-num"><?php echo esc_html($label); ?></th>
                    <?php } ?>
                    <th class="af-num"><?php esc_html_e('Schedules', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Trainings', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Group participants', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Schedule total', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Training total', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Group total', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Total', 'latepoint-agent-fees'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grand_total = 0;
                foreach ($agents as $agent) {
                    $s              = $stats[$agent->id];
                    $schedule_total = $s['schedules'] * $schedule_fee;
                    $training_total = $s['trainings'] * $training_fee;
                    $group_total    = $s['group_participants'] * $group_fee;
                    $grand_total   += $schedule_total + $training_total + $group_total; ?>
                    <tr>
                        <td><?php echo esc_html($agent->full_name); ?></td>
                        <?php foreach (array_keys($shifts) as $i) { ?>
                            <td class="af-num"><?php echo esc_html($units($s['shift_units'][$i])); ?></td>
                        <?php } ?>
                        <td class="af-num"><?php echo esc_html($units($s['schedules'])); ?></td>
                        <td class="af-num">
                            <?php echo (int) $s['trainings']; ?>
                            <?php if ($s['bookings'] > $s['trainings']) { ?>
                                <span class="af-muted">(<?php printf(esc_html__('%d bookings', 'latepoint-agent-fees'), (int) $s['bookings']); ?>)</span>
                            <?php } ?>
                        </td>
                        <td class="af-num"><?php echo (int) $s['group_participants']; ?></td>
                        <td class="af-num"><?php echo esc_html($money($schedule_total)); ?></td>
                        <td class="af-num"><?php echo esc_html($money($training_total)); ?></td>
                        <td class="af-num"><?php echo esc_html($money($group_total)); ?></td>
                        <td class="af-num af-total"><?php echo esc_html($money($schedule_total + $training_total + $group_total)); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="<?php echo 7 + count($shifts); ?>" class="af-num af-total"><?php esc_html_e('All agents', 'latepoint-agent-fees'); ?></td>
                    <td class="af-num af-total"><?php echo esc_html($money($grand_total)); ?></td>
                </tr>
            </tfoot>
        </table>
    </form>

    <?php foreach ($agents as $agent) {
        $s = $stats[$agent->id]; ?>
        <details class="af-agent">
            <summary>
                <?php echo esc_html($agent->full_name); ?>
                — <?php printf(esc_html__('%1$s schedules, %2$d trainings', 'latepoint-agent-fees'), $units($s['schedules']), (int) $s['trainings']); ?>
                <?php if ($s['group_participants']) {
                    printf(esc_html__(', %d group participants', 'latepoint-agent-fees'), (int) $s['group_participants']);
                } ?>
            </summary>
            <div>
                <?php if (empty($s['days'])) { ?>
                    <p class="af-muted"><?php esc_html_e('No working hours or trainings this month.', 'latepoint-agent-fees'); ?></p>
                <?php } else { ?>
                    <table class="af-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'latepoint-agent-fees'); ?></th>
                                <th><?php esc_html_e('Working hours', 'latepoint-agent-fees'); ?></th>
                                <?php foreach ($shift_labels as $label) { ?>
                                    <th class="af-num"><?php echo esc_html($label); ?></th>
                                <?php } ?>
                                <th class="af-num"><?php esc_html_e('Schedules', 'latepoint-agent-fees'); ?></th>
                                <th class="af-num"><?php esc_html_e('Bookings', 'latepoint-agent-fees'); ?></th>
                                <th><?php esc_html_e('Trainings', 'latepoint-agent-fees'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($s['days'] as $d) {
                                $calc = $d['calc']; ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n('D, j M', strtotime($d['date']))); ?></td>
                                    <td><?php echo $d['periods'] ? esc_html(implode(', ', $d['periods'])) : '<span class="af-muted">—</span>'; ?></td>
                                    <?php foreach ($shifts as $i => $shift) {
                                        $len = $shift[1] - $shift[0];
                                        $pct = (int) round($calc['covered'][$i] * 100 / $len); ?>
                                        <td class="af-num <?php echo $calc['hits'][$i] ? 'af-hit' : 'af-muted'; ?>">
                                            <?php echo esc_html($m2hm($calc['covered'][$i]) . ' · ' . $pct . '%'); ?>
                                        </td>
                                    <?php } ?>
                                    <td class="af-num">
                                        <?php echo esc_html($units($calc['schedules'])); ?>
                                        <?php if ($calc['combined']) { ?>
                                            <span class="af-combined"><?php esc_html_e('(combined)', 'latepoint-agent-fees'); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td class="af-num"><?php echo (int) array_sum(array_column($d['sessions'], 'bookings')); ?></td>
                                    <td>
                                        <?php
                                        $labels = array_map(function ($sess) {
                                            $label = $sess['time'];
                                            if ($sess['bookings'] > 1) {
                                                $label .= '×' . (int) $sess['bookings'];
                                            }
                                            if ($sess['service'] !== '') {
                                                $label .= " {$sess['service']}";
                                            }
                                            if ($sess['service_id'] === LATEPOINT_AGENT_FEES_GROUP_SERVICE) {
                                                $label .= ' ' . __('(group)', 'latepoint-agent-fees');
                                            }
                                            if ($sess['no_show']) {
                                                $label .= ' ' . __('(no-show)', 'latepoint-agent-fees');
                                            }
                                            return $label;
                                        }, $d['sessions']);
                                        echo $labels ? esc_html(implode(', ', $labels)) : '<span class="af-muted">—</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="af-num af-total"><?php esc_html_e('Total', 'latepoint-agent-fees'); ?></td>
                                <?php foreach (array_keys($shifts) as $i) { ?>
                                    <td class="af-num af-total"><?php printf(esc_html__('%s schedules', 'latepoint-agent-fees'), esc_html($units($s['shift_units'][$i]))); ?></td>
                                <?php } ?>
                                <td class="af-num af-total"><?php echo esc_html($units($s['schedules'])); ?></td>
                                <td class="af-num af-total"><?php echo (int) ($s['bookings'] + $s['group_participants']); ?></td>
                                <td class="af-total">
                                    <?php printf(esc_html__('%d trainings', 'latepoint-agent-fees'), (int) $s['trainings']); ?>
                                    <?php if ($s['group_participants']) {
                                        printf(esc_html__(', %d group participants', 'latepoint-agent-fees'), (int) $s['group_participants']);
                                    } ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </details>
    <?php } ?>
</div>
