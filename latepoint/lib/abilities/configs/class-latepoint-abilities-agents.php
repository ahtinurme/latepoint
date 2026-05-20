<?php
/**
 * LatePoint Abilities — Agents module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesAgents {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/agents/';

		require_once $base . 'abstract-agent-ability.php';
		require_once $base . 'get-agents.php';
		require_once $base . 'get-agent.php';
		require_once $base . 'create-agent.php';
		require_once $base . 'update-agent.php';
		require_once $base . 'delete-agent.php';
		require_once $base . 'enable-agent.php';
		require_once $base . 'disable-agent.php';
		require_once $base . 'get-agent-services.php';
		require_once $base . 'get-agent-bookings.php';
		require_once $base . 'get-agent-revenue.php';

		return [
			new LatePointAbilityGetAgents(),
			new LatePointAbilityGetAgent(),
			new LatePointAbilityCreateAgent(),
			new LatePointAbilityUpdateAgent(),
			new LatePointAbilityDeleteAgent(),
			new LatePointAbilityEnableAgent(),
			new LatePointAbilityDisableAgent(),
			new LatePointAbilityGetAgentServices(),
			new LatePointAbilityGetAgentBookings(),
			new LatePointAbilityGetAgentRevenue(),
		];
	}
}
