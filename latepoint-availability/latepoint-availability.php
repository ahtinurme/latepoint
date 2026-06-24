<?php
/**
 * Plugin Name: LatePoint Availability Overview
 * Description: One week-at-a-glance grid to view and edit every agent's working hours for
 *              specific calendar dates (like Timma's worktimes page), with week navigation.
 *              Adds an "Availability" item to the LatePoint side menu. Editing a date stores
 *              a date-specific override; matching the recurring weekly schedule clears it.
 *              Split-shift days (more than one period) stay read-only and are managed on the
 *              agent's own page.
 * Version:     1.0.0
 * Author:      Yumefit
 * Text Domain: latepoint
 * Requires Plugins: latepoint
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('latepoint_includes', function () {
    include_once __DIR__ . '/lib/controllers/availability_controller.php';
});

add_filter('latepoint_side_menu', function ($menus) {
    if (OsAuthHelper::get_current_user()->backend_user_type !== LATEPOINT_USER_TYPE_ADMIN) {
        return $menus;
    }
    $menus[] = [
        'id'    => 'availability',
        'label' => __('Availability', 'latepoint'),
        'icon'  => 'latepoint-icon latepoint-icon-clock',
        'link'  => OsRouterHelper::build_link(['availability', 'index']),
    ];
    return $menus;
});
