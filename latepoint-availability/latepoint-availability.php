<?php
/**
 * Plugin Name: LatePoint Addon - Availability Overview
 * Description: One week-at-a-glance grid to view and edit every agent's working hours for
 *              specific calendar dates (like Timma's worktimes page), with week navigation.
 *              Adds an "Availability" item to the LatePoint side menu. Each date can hold
 *              several periods (split shifts); editing a date stores a date-specific
 *              override, while matching the recurring weekly schedule clears it.
 *              Also adds a monthly "Graafik" planning page (like the old Excel sheet):
 *              agents mark their next-month shift availability (invite emailed on the
 *              15th), info@ is notified of submissions, admin assigns shifts in the
 *              summary and confirms — which writes the actual LatePoint work periods
 *              and emails each agent their schedule.
 * Version:     1.1.0
 * Author:      Yumefit
 * Text Domain: latepoint-availability
 * Requires Plugins: latepoint
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('latepoint_includes', function () {
    include_once __DIR__ . '/lib/controllers/availability_controller.php';
    include_once __DIR__ . '/lib/controllers/schedule_controller.php';
});

add_filter('latepoint_side_menu', function ($menus) {
    $user_type = OsAuthHelper::get_current_user()->backend_user_type;
    if ($user_type === LATEPOINT_USER_TYPE_ADMIN) {
        $menus[] = [
            'id'    => 'availability',
            'label' => __('Availability', 'latepoint-availability'),
            'icon'  => 'latepoint-icon latepoint-icon-clock',
            'link'  => OsRouterHelper::build_link(['availability', 'index']),
        ];
    }
    if (in_array($user_type, [LATEPOINT_USER_TYPE_ADMIN, LATEPOINT_USER_TYPE_AGENT], true)) {
        $menus[] = [
            'id'    => 'schedule',
            'label' => 'Graafik',
            'icon'  => 'latepoint-icon latepoint-icon-calendar2',
            'link'  => OsRouterHelper::build_link(['schedule', 'index']),
        ];
    }
    return $menus;
});

// The schedule page is open to any backend user (admin or agent); admin-only
// actions are enforced inside the controller.
add_filter('latepoint_get_capabilities_for_controller_action', function ($capabilities, $controller_name) {
    return $controller_name === 'OsScheduleController' ? [] : $capabilities;
}, 10, 2);

/** @return array{availability: array, submitted: array, assignments: array, confirmed_at: string} */
function yumefit_schedule_data(string $month): array {
    $data = get_option('yumefit_schedule_' . $month, []);
    return (is_array($data) ? $data : []) + [
        'availability' => [],
        'submitted'    => [],
        'assignments'  => [],
        'confirmed_at' => '',
    ];
}

/**
 * Admin-picked roster (option `yumefit_schedule_agents`): only these agents get
 * invite emails and appear in the Graafik table. Option never saved = all active.
 *
 * @param OsAgentModel[] $agents
 * @return OsAgentModel[]
 */
function yumefit_schedule_filter_agents(array $agents): array {
    $ids = get_option('yumefit_schedule_agents', null);
    if (!is_array($ids)) {
        return $agents;
    }
    $ids = array_map('intval', $ids);
    return array_values(array_filter($agents, fn($a) => in_array((int) $a->id, $ids, true)));
}

function yumefit_schedule_month_title(string $month): string {
    $names = ['jaanuar', 'veebruar', 'märts', 'aprill', 'mai', 'juuni', 'juuli', 'august', 'september', 'oktoober', 'november', 'detsember'];
    [$year, $month_num] = explode('-', $month);
    return $names[(int) $month_num - 1] . ' ' . $year;
}

/* On the 15th of each month every active agent gets an email asking to enter
 * next month's availability on the Graafik page. Sent once per target month
 * (flag option); the >= 15 check makes a missed cron day catch up later. */
add_action('init', function (): void {
    if (!wp_next_scheduled('yumefit_schedule_invite')) {
        wp_schedule_event(time() + 300, 'daily', 'yumefit_schedule_invite');
    }
});

add_action('yumefit_schedule_invite', function (): void {
    if (!class_exists('OsAgentModel') || !class_exists('OsRouterHelper')) {
        return;
    }
    if ((int) current_time('j') < 15) {
        return;
    }
    $month = (new DateTimeImmutable('first day of next month', wp_timezone()))->format('Y-m');
    $flag  = 'yumefit_schedule_invited_' . $month;
    if (get_option($flag)) {
        return;
    }

    $agents = (new OsAgentModel())->where(['status' => LATEPOINT_AGENT_STATUS_ACTIVE])->get_results_as_models();
    $agents = yumefit_schedule_filter_agents(is_array($agents) ? $agents : []);
    $link   = OsRouterHelper::build_link(['schedule', 'index'], ['month' => $month]);
    $title  = yumefit_schedule_month_title($month);

    foreach (is_array($agents) ? $agents : [] as $agent) {
        if (!is_email($agent->email)) {
            continue;
        }
        wp_mail(
            $agent->email,
            "Yumefit: palun sisesta oma tööaja soovid — {$title}",
            "Tere, {$agent->first_name}!\n\nPalun märgi oma saadavus järgmiseks kuuks ({$title}) Yumefit graafiku lehel:\n\n{$link}\n\nKui valikud on tehtud, vajuta lehel „Saada saadavus”.\n\nYumefit stuudio"
        );
    }
    update_option($flag, current_time('mysql'), false);
});
