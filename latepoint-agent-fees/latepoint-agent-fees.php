<?php
/**
 * Plugin Name: LatePoint Addon - Agent Fees
 * Description: Monthly fee report per agent: a fee for each opened schedule (9-14 / 14-20
 *              shifts, counted when at least 80% covered, or combined across both) and a fee
 *              for each given training (happened + no-show; cancelled excluded). Fees are
 *              editable per agent; the report shows the per-day stats behind every total.
 * Version:     1.0.0
 * Author:      Yumefit
 * Text Domain: latepoint-agent-fees
 * Requires Plugins: latepoint
 */

if (!defined('ABSPATH')) {
    exit;
}

// Jooga rühmatreening (same id as YUMEFIT_JOOGA_SERVICE in yumefit-rules):
// billed per participant at the group fee, not per session at the training fee.
define('LATEPOINT_AGENT_FEES_GROUP_SERVICE', 7);

add_action('latepoint_includes', function () {
    include_once __DIR__ . '/lib/fees_math.php';
    include_once __DIR__ . '/lib/controllers/agent_fees_controller.php';
});

add_filter('latepoint_side_menu', function ($menus) {
    if (OsAuthHelper::get_current_user()->backend_user_type !== LATEPOINT_USER_TYPE_ADMIN) {
        return $menus;
    }
    $menus[] = [
        'id'    => 'agent_fees',
        'label' => __('Agent Fees', 'latepoint-agent-fees'),
        'icon'  => 'latepoint-icon latepoint-icon-credit-card',
        'link'  => OsRouterHelper::build_link(['agent_fees', 'index']),
    ];
    return $menus;
});
