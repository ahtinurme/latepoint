<?php
/**
 * @var string         $month             YYYY-MM
 * @var string         $month_title
 * @var string         $prev_month
 * @var string         $next_month
 * @var array          $days              list of ['d' => int, 'wd' => int 1-7]
 * @var int            $days_in_month
 * @var OsAgentModel[] $agents            participating agents (rows in the table)
 * @var OsAgentModel[] $all_agents        every active agent (admin roster checkboxes)
 * @var array          $data              ['availability','submitted','assignments','confirmed_at']
 * @var array          $summary           [shift][day] => ['available' => int[], 'assigned' => int]
 * @var array          $uncovered_per_day [day] => 0|1|2
 * @var array          $stats             ['full_uncovered','half_uncovered','covered']
 * @var bool           $is_admin
 * @var int            $my_agent_id
 * @var bool           $saved
 * @var bool           $confirmed
 */
if (!defined('ABSPATH')) {
    exit;
}

$shifts    = OsScheduleController::SHIFTS;
$letters   = [1 => 'E', 2 => 'T', 3 => 'K', 4 => 'N', 5 => 'R', 6 => 'L', 7 => 'P'];
$prev_link = OsRouterHelper::build_link(['schedule', 'index'], ['month' => $prev_month]);
$next_link = OsRouterHelper::build_link(['schedule', 'index'], ['month' => $next_month]);
$colspan   = count($days) + 1;

$agent_names = [];
foreach ($agents as $a) {
    $agent_names[(int) $a->id] = $a->first_name;
}

$status = function (array $cell): string {
    if ($cell['assigned']) {
        return 'ts-ok';
    }
    return $cell['available'] ? 'ts-maybe' : 'ts-none';
};
?>
<style>
    .ts-wrap { padding: 20px; }
    .ts-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .ts-toolbar .ts-range { font-weight: 600; font-size: 16px; text-transform: capitalize; }
    .ts-badge { background: #e7f7ed; color: #1d7a45; border: 1px solid #b6e4c6; border-radius: 6px; padding: 4px 10px; font-size: 12px; }
    .ts-hint { color: #6b7280; margin: 0 0 14px; max-width: 900px; }
    .ts-notice { background: #e7f7ed; border: 1px solid #b6e4c6; color: #1d7a45; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; }
    .ts-table { border-collapse: collapse; background: #fff; font-size: 12px; }
    .ts-table th, .ts-table td { border: 1px solid #e6e9f0; padding: 3px 4px; text-align: center; min-width: 34px; }
    .ts-table thead th { background: #f7f8fc; font-weight: 600; }
    .ts-table .ts-we { background: #f1f3f8; }
    .ts-table .ts-label { text-align: left; white-space: nowrap; font-weight: 600; position: sticky; left: 0; background: #fff; z-index: 1; min-width: 130px; padding-left: 8px; }
    .ts-table thead .ts-label { background: #f7f8fc; z-index: 2; }
    .ts-agent-row td { background: #eef3ff; text-align: left; font-weight: 700; padding: 5px 8px; }
    .ts-agent-row small { font-weight: 400; color: #6b7280; margin-left: 10px; }
    .ts-sep td { background: #2d3748; color: #fff; text-align: left; font-weight: 700; padding: 6px 8px; letter-spacing: 0.05em; }
    .ts-check { color: #1d7a45; font-weight: 700; }
    .ts-ok { background: #e7f7ed; }
    .ts-maybe { background: #fff7e0; }
    .ts-none { background: #fdecec; }
    .ts-u { font-weight: 700; }
    .ts-u-bad { color: #b3261e; }
    .ts-table select { font-size: 11px; max-width: 82px; border: 1px solid #d5d9e3; border-radius: 4px; padding: 1px 2px; background: transparent; }
    .ts-table input[type="checkbox"] { margin: 0; }
    .ts-time { display: none; width: 46px; font-size: 10px; border: 1px solid #d5d9e3; border-radius: 3px; padding: 0 2px; margin-top: 2px; text-align: center; }
    .ts-table td:has(input[type="checkbox"]:checked) .ts-time { display: inline-block; }
    .ts-time-label { color: #1d7a45; font-size: 10px; white-space: nowrap; }
    .ts-stats { margin-top: 16px; background: #fff; border: 1px solid #e6e9f0; border-radius: 8px; padding: 12px 16px; display: inline-block; }
    .ts-stats div { display: flex; align-items: center; gap: 8px; padding: 2px 0; }
    .ts-dot { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
    .ts-actions { position: sticky; bottom: 0; background: #fff; padding: 14px 0; margin-top: 16px; border-top: 1px solid #e6e9f0; display: flex; gap: 10px; }
    .ts-roster { background: #fff; border: 1px solid #e6e9f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .ts-roster label { display: flex; align-items: center; gap: 4px; }
    .ts-roster small { color: #6b7280; flex-basis: 100%; }
</style>

<div class="ts-wrap">
    <?php if ($saved) { ?>
        <div class="ts-notice">Salvestatud.</div>
    <?php } ?>
    <?php if ($confirmed) { ?>
        <div class="ts-notice">Graafik on kinnitatud, tööajad salvestatud ja treeneritele saadetud e-kirjad.</div>
    <?php } ?>

    <div class="ts-toolbar">
        <a href="<?php echo esc_url($prev_link); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-left"></i></a>
        <span class="ts-range"><?php echo esc_html($month_title); ?></span>
        <a href="<?php echo esc_url($next_link); ?>" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-chevron-right"></i></a>
        <?php if (!empty($data['confirmed_at'])) { ?>
            <span class="ts-badge">Kinnitatud <?php echo esc_html(date('d.m.Y H:i', strtotime($data['confirmed_at']))); ?></span>
        <?php } ?>
    </div>

    <p class="ts-hint">
        <?php if ($is_admin) { ?>
            Kokkuvõttes vali igale vahetusele treener ja vajuta „Salvesta jaotus”. „Kinnita graafik” salvestab tööajad LatePointi ja saadab igale treenerile tema graafiku e-kirjaga.
        <?php } else { ?>
            Märgi linnukesega vahetused, millal saad töötada, ja vajuta „Saada saadavus”. Kui saad mõnel päeval ainult osa vahetusest, kirjuta linnukese alla kellaajad (nt 10-14). Teiste treenerite saadavust näed, aga muuta ei saa.
        <?php } ?>
    </p>

    <?php if ($is_admin) {
        $participating_ids = array_map(fn($a) => (int) $a->id, $agents); ?>
        <form class="ts-roster" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="latepoint_route_call">
            <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('schedule', 'save_agents')); ?>">
            <input type="hidden" name="params[month]" value="<?php echo esc_attr($month); ?>">
            <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('yumefit_schedule_agents')); ?>">
            <strong>Graafikus osalevad treenerid:</strong>
            <?php foreach ($all_agents as $agent) { ?>
                <label>
                    <input type="checkbox" name="params[agent_ids][]" value="<?php echo (int) $agent->id; ?>" <?php checked(in_array((int) $agent->id, $participating_ids, true)); ?>>
                    <?php echo esc_html($agent->full_name); ?>
                </label>
            <?php } ?>
            <button type="submit" class="latepoint-btn latepoint-btn-outline latepoint-btn-sm">Salvesta valik</button>
            <small>Ainult valitud treenerid saavad 15. kuupäeval saadavuse e-kirja, on tabelis ja saavad kinnitatud graafiku.</small>
        </form>
    <?php } ?>

    <form id="ts-av-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="latepoint_route_call">
        <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('schedule', 'save_availability')); ?>">
        <input type="hidden" name="params[month]" value="<?php echo esc_attr($month); ?>">
        <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('yumefit_schedule_availability')); ?>">
        <?php foreach ($agents as $agent) {
            if ($is_admin || (int) $agent->id === $my_agent_id) { ?>
                <input type="hidden" name="params[availability][<?php echo (int) $agent->id; ?>][present]" value="1">
            <?php }
        } ?>
    </form>
    <?php if ($is_admin) { ?>
        <form id="ts-as-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="latepoint_route_call">
            <input type="hidden" name="route_name" value="<?php echo esc_attr(OsRouterHelper::build_route_name('schedule', 'save_assignments')); ?>">
            <input type="hidden" name="params[month]" value="<?php echo esc_attr($month); ?>">
            <input type="hidden" name="params[_wpnonce]" value="<?php echo esc_attr(wp_create_nonce('yumefit_schedule_assignments')); ?>">
        </form>
    <?php } ?>

    <div style="overflow-x:auto;">
        <table class="ts-table">
            <thead>
                <tr>
                    <th class="ts-label"></th>
                    <?php foreach ($days as $day) { ?>
                        <th class="<?php echo $day['wd'] >= 6 ? 'ts-we' : ''; ?>"><?php echo esc_html($letters[$day['wd']]); ?></th>
                    <?php } ?>
                </tr>
                <tr>
                    <th class="ts-label">Kuupäev</th>
                    <?php foreach ($days as $day) { ?>
                        <th class="<?php echo $day['wd'] >= 6 ? 'ts-we' : ''; ?>"><?php echo (int) $day['d']; ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $agent) {
                    $aid      = (int) $agent->id;
                    $editable = $is_admin || $aid === $my_agent_id;
                    $sent_at  = $data['submitted'][$aid] ?? ''; ?>
                    <tr class="ts-agent-row">
                        <td colspan="<?php echo (int) $colspan; ?>">
                            <?php echo esc_html(mb_strtoupper($agent->full_name)); ?>
                            <?php if ($sent_at) { ?>
                                <small>saadavus saadetud <?php echo esc_html(date('d.m.Y H:i', strtotime($sent_at))); ?></small>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php foreach ($shifts as $shift_key => $shift) { ?>
                        <tr>
                            <td class="ts-label"><?php echo esc_html($shift['label']); ?></td>
                            <?php foreach ($days as $day) {
                                $marked = in_array($day['d'], $data['availability'][$aid][$shift_key] ?? [], true);
                                $custom = $data['availability'][$aid]['times'][$shift_key][$day['d']] ?? null; ?>
                                <td class="<?php echo $day['wd'] >= 6 ? 'ts-we' : ''; ?>">
                                    <?php if ($editable) { ?>
                                        <input type="checkbox" form="ts-av-form"
                                               name="params[availability][<?php echo $aid; ?>][<?php echo esc_attr($shift_key); ?>][]"
                                               value="<?php echo (int) $day['d']; ?>" <?php checked($marked); ?>>
                                        <input type="text" form="ts-av-form" class="ts-time"
                                               name="params[availability][<?php echo $aid; ?>][times][<?php echo esc_attr($shift_key); ?>][<?php echo (int) $day['d']; ?>]"
                                               value="<?php echo $custom ? esc_attr(OsScheduleController::range_label($custom)) : ''; ?>"
                                               placeholder="<?php echo esc_attr($shift['placeholder']); ?>" title="Kellaajad sellel päeval, nt 10-14">
                                    <?php } elseif ($marked) { ?>
                                        <?php echo $custom ? '<span class="ts-time-label">' . esc_html(OsScheduleController::range_label($custom)) . '</span>' : '<span class="ts-check">✓</span>'; ?>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php }
                } ?>

                <tr class="ts-sep">
                    <td colspan="<?php echo (int) $colspan; ?>">KOKKUVÕTE</td>
                </tr>
                <tr>
                    <td class="ts-label">Katmata vahetusi</td>
                    <?php foreach ($days as $day) {
                        $u = $uncovered_per_day[$day['d']]; ?>
                        <td class="ts-u <?php echo $u ? 'ts-u-bad' : ''; ?>"><?php echo (int) $u; ?></td>
                    <?php } ?>
                </tr>
                <?php foreach ($shifts as $shift_key => $shift) { ?>
                    <tr>
                        <td class="ts-label"><?php echo esc_html($shift['label']); ?></td>
                        <?php foreach ($days as $day) {
                            $cell = $summary[$shift_key][$day['d']]; ?>
                            <td class="<?php echo esc_attr($status($cell)); ?>">
                                <?php if ($is_admin) { ?>
                                    <select form="ts-as-form" name="params[assign][<?php echo esc_attr($shift_key); ?>][<?php echo (int) $day['d']; ?>]">
                                        <option value="">—</option>
                                        <?php
                                        $options = $cell['available'];
                                        if ($cell['assigned'] && !in_array($cell['assigned'], $options, true)) {
                                            $options[] = $cell['assigned'];
                                        }
                                        foreach ($options as $option_id) { ?>
                                            <option value="<?php echo (int) $option_id; ?>" <?php selected($cell['assigned'], $option_id); ?>>
                                                <?php echo esc_html($agent_names[$option_id] ?? "#{$option_id}"); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                <?php } else {
                                    echo esc_html($cell['assigned'] ? ($agent_names[$cell['assigned']] ?? '') : ($cell['available'] ? '?' : '–'));
                                } ?>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="ts-stats">
        <div><span class="ts-dot" style="background:#f5b7b1;"></span> Terve päev katmata: <strong><?php echo (int) $stats['full_uncovered']; ?></strong> (<?php echo round($stats['full_uncovered'] / $days_in_month * 100); ?>%)</div>
        <div><span class="ts-dot" style="background:#fde9a9;"></span> Pool päeva katmata: <strong><?php echo (int) $stats['half_uncovered']; ?></strong> (<?php echo round($stats['half_uncovered'] / $days_in_month * 100); ?>%)</div>
        <div><span class="ts-dot" style="background:#b6e4c6;"></span> Päev kaetud: <strong><?php echo (int) $stats['covered']; ?></strong> (<?php echo round($stats['covered'] / $days_in_month * 100); ?>%)</div>
        <div>Kokku päevi: <strong><?php echo (int) $days_in_month; ?></strong></div>
    </div>

    <div class="ts-actions">
        <?php if ($is_admin || isset($agent_names[$my_agent_id])) { ?>
            <button type="submit" form="ts-av-form" class="latepoint-btn latepoint-btn-primary">Saada saadavus</button>
        <?php } ?>
        <?php if ($is_admin) { ?>
            <button type="submit" form="ts-as-form" class="latepoint-btn latepoint-btn-outline">Salvesta jaotus</button>
            <button type="submit" form="ts-as-form" name="params[confirm]" value="1" id="ts-confirm-btn" class="latepoint-btn latepoint-btn-primary">Kinnita graafik ja saada e-kirjad</button>
        <?php } ?>
    </div>
</div>

<script>
    document.getElementById('ts-confirm-btn')?.addEventListener('click', function (e) {
        if (!confirm('Kinnitan graafiku: tööajad salvestatakse LatePointi ja treeneritele saadetakse e-kirjad. Jätkan?')) {
            e.preventDefault();
        }
    });
</script>
