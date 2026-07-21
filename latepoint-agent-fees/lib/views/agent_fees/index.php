<?php
/**
 * @var OsAgentModel[] $agents
 * @var array          $fees        [agent_id => ['shifts' => float[], 'training' => float]]
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
            esc_html__('A schedule counts when at least 80%% of a shift (%1$s or %2$s) is covered by working hours; a shorter day totalling at least 4 hours inside 09:00–20:00 counts as one combined schedule, billed at the fee of the shift with more covered time. Trainings count happened and no-show bookings; cancelled are excluded. Group bookings at the same time are one training.', 'latepoint-agent-fees'),
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

        <table class="af-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Agent', 'latepoint-agent-fees'); ?></th>
                    <?php foreach ($shift_labels as $label) { ?>
                        <th class="af-num"><?php printf(esc_html__('Fee %s', 'latepoint-agent-fees'), esc_html($label)); ?></th>
                    <?php } ?>
                    <th class="af-num"><?php esc_html_e('Training fee', 'latepoint-agent-fees'); ?></th>
                    <?php foreach ($shift_labels as $label) { ?>
                        <th class="af-num"><?php echo esc_html($label); ?></th>
                    <?php } ?>
                    <th class="af-num"><?php esc_html_e('Trainings', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Schedule total', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Training total', 'latepoint-agent-fees'); ?></th>
                    <th class="af-num"><?php esc_html_e('Total', 'latepoint-agent-fees'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grand_total = 0;
                foreach ($agents as $agent) {
                    $s              = $stats[$agent->id];
                    $shift_fees     = array_map(fn($i) => (float) ($fees[$agent->id]['shifts'][$i] ?? 0), array_keys($shifts));
                    $training_fee   = (float) ($fees[$agent->id]['training'] ?? 0);
                    $schedule_total = array_sum(array_map(fn($i) => $s['shift_counts'][$i] * $shift_fees[$i], array_keys($shifts)));
                    $training_total = $s['trainings'] * $training_fee;
                    $grand_total   += $schedule_total + $training_total; ?>
                    <tr>
                        <td><?php echo esc_html($agent->full_name); ?></td>
                        <?php foreach (array_keys($shifts) as $i) { ?>
                            <td class="af-num"><input type="number" step="0.01" min="0" name="params[fees][<?php echo (int) $agent->id; ?>][shifts][<?php echo (int) $i; ?>]" value="<?php echo esc_attr($shift_fees[$i] ?: ''); ?>"> €</td>
                        <?php } ?>
                        <td class="af-num"><input type="number" step="0.01" min="0" name="params[fees][<?php echo (int) $agent->id; ?>][training]" value="<?php echo esc_attr($training_fee ?: ''); ?>"> €</td>
                        <?php foreach (array_keys($shifts) as $i) { ?>
                            <td class="af-num"><?php echo (int) $s['shift_counts'][$i]; ?></td>
                        <?php } ?>
                        <td class="af-num">
                            <?php echo (int) $s['trainings']; ?>
                            <?php if ($s['bookings'] > $s['trainings']) { ?>
                                <span class="af-muted">(<?php printf(esc_html__('%d bookings', 'latepoint-agent-fees'), (int) $s['bookings']); ?>)</span>
                            <?php } ?>
                        </td>
                        <td class="af-num"><?php echo esc_html($money($schedule_total)); ?></td>
                        <td class="af-num"><?php echo esc_html($money($training_total)); ?></td>
                        <td class="af-num af-total"><?php echo esc_html($money($schedule_total + $training_total)); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="<?php echo 6 + 2 * count($shifts); ?>" class="af-num af-total"><?php esc_html_e('All agents', 'latepoint-agent-fees'); ?></td>
                    <td class="af-num af-total"><?php echo esc_html($money($grand_total)); ?></td>
                </tr>
            </tfoot>
        </table>

        <button type="submit" class="latepoint-btn latepoint-btn-primary" style="margin-bottom: 24px;"><?php esc_html_e('Save Fees', 'latepoint-agent-fees'); ?></button>
    </form>

    <?php foreach ($agents as $agent) {
        $s = $stats[$agent->id]; ?>
        <details class="af-agent">
            <summary>
                <?php echo esc_html($agent->full_name); ?>
                — <?php printf(esc_html__('%1$d schedules, %2$d trainings', 'latepoint-agent-fees'), (int) $s['schedules'], (int) $s['trainings']); ?>
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
                                        <?php echo (int) $calc['schedules']; ?>
                                        <?php if ($calc['combined']) { ?>
                                            <span class="af-combined"><?php printf(
                                                esc_html__('(combined, %s fee)', 'latepoint-agent-fees'),
                                                esc_html($shift_labels[array_search(1, $calc['shift_counts'])])
                                            ); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php
                                        $labels = array_map(function ($sess) {
                                            $label = $sess['time'];
                                            if ($sess['bookings'] > 1) {
                                                $label .= '×' . (int) $sess['bookings'];
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
                                    <td class="af-num af-total"><?php printf(esc_html__('%d schedules', 'latepoint-agent-fees'), (int) $s['shift_counts'][$i]); ?></td>
                                <?php } ?>
                                <td class="af-num af-total"><?php echo (int) $s['schedules']; ?></td>
                                <td class="af-total"><?php printf(esc_html__('%d trainings', 'latepoint-agent-fees'), (int) $s['trainings']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </details>
    <?php } ?>
</div>
