<?php
/**
 * LatePoint Abilities — Main loader & hook wiring.
 *
 * Registers the LatePoint category and all abilities with the WordPress
 * Abilities API (requires WordPress 6.9+). The guarded loader in latepoint.php
 * ensures this file is only included when the API is available.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilities {

	/** @var string[] Config class map: module slug => class name */
	private static array $config_modules = [
		'bookings'   => 'LatePointAbilitiesBookings',
		'customers'  => 'LatePointAbilitiesCustomers',
		'services'   => 'LatePointAbilitiesServices',
		'agents'     => 'LatePointAbilitiesAgents',
		'orders'     => 'LatePointAbilitiesOrders',
		'locations'  => 'LatePointAbilitiesLocations',
		'calendar'   => 'LatePointAbilitiesCalendar',
		'analytics'  => 'LatePointAbilitiesAnalytics',
		'activities' => 'LatePointAbilitiesActivities',
	];

	/**
	 * Boot: include module files and wire hooks.
	 */
	public static function init(): void {
		self::include_modules();

		// Reset LatePoint's current user on every request to ensure it reflects the latest WP user state.
		// This is crucial for accurate permission checks in abilities, especially when user roles or capabilities have changed during the request lifecycle.
		add_action( 'set_current_user', [ OsAuthHelper::class, 'reset_current_user' ], 1 );

		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_all' ] );

		// Wire the dedicated MCP server once all plugins are fully loaded. We use a
		// late plugins_loaded priority (99, matching SureForms) so the MCP Adapter's
		// classes are reliably available to mcp_adapter_enabled() regardless of plugin
		// load order — important when another active plugin bundles its own adapter copy.
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_register_mcp_server' ], 99999 );
	}

	/**
	 * Attach the dedicated MCP server registration when the adapter is active.
	 */
	public static function maybe_register_mcp_server(): void {
		if ( self::mcp_adapter_enabled() ) {
			add_action( 'mcp_adapter_init', [ __CLASS__, 'register_mcp_server' ] );
		}
	}

	/**
	 * Whether the dedicated LatePoint MCP server should be registered.
	 *
	 * Requires the WordPress Abilities API and the MCP Adapter (WP\MCP\Plugin)
	 * to be available, and the MCP server toggle to be on.
	 */
	public static function mcp_adapter_enabled(): bool {
		return function_exists( 'wp_register_ability' )
			&& class_exists( 'WP\\MCP\\Plugin' )
			&& OsSettingsHelper::is_on( 'latepoint_mcp_server' );
	}

	/**
	 * Register a dedicated LatePoint MCP server with the MCP Adapter.
	 *
	 * Endpoint: {site_url}/wp-json/latepoint/v1/mcp — exposes every registered
	 * latepoint/* ability as a tool. register_all() only registers abilities the
	 * Enable Edit / Enable Delete toggles allow, so the published tool set follows
	 * those toggles automatically (read-only when both are off; create/update and
	 * delete tools appear as those toggles are enabled).
	 *
	 * @param \WP\MCP\Core\McpAdapter $adapter The MCP adapter instance.
	 */
	public static function register_mcp_server( $adapter ): void {
		$tools = [];
		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();
			if ( 0 === strpos( $name, 'latepoint/' ) ) {
				$tools[] = $name;
			}
		}

		/**
		 * Filters the tool ids exposed by the dedicated LatePoint MCP server.
		 *
		 * Defaults to every registered latepoint/* ability (already gated by the
		 * Enable Edit / Enable Delete toggles). Return a subset to publish fewer
		 * tools to AI clients.
		 *
		 * @param string[] $tools Ability ids exposed by the dedicated MCP server.
		 */
		$tools = apply_filters( 'latepoint_mcp_server_tool_ids', $tools );
		if ( empty( $tools ) ) {
			return;
		}

		$transport_class = class_exists( '\\WP\\MCP\\Transport\\HttpTransport' )
			? \WP\MCP\Transport\HttpTransport::class
			: \WP\MCP\Transport\Http\RestTransport::class;

		$adapter->create_server(
			'latepoint',
			'latepoint/v1',
			'mcp',
			OsSettingsHelper::get_brand_name() . ' ' . __( 'MCP Server', 'latepoint' ),
			sprintf(
				/* translators: %s: brand name. */
				__( '%s MCP Server for managing bookings, customers, services, agents, locations, and orders.', 'latepoint' ),
				OsSettingsHelper::get_brand_name()
			),
			LATEPOINT_VERSION,
			[ $transport_class ],
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$tools,
			[],
			[]
		);
	}

	/**
	 * Include abstract base and all config module files.
	 */
	private static function include_modules(): void {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/';
		require_once $base . 'abstract-ability.php';
		foreach ( array_keys( self::$config_modules ) as $slug ) {
			require_once $base . 'configs/class-latepoint-abilities-' . $slug . '.php';
		}
	}

	/**
	 * Register the top-level LatePoint category.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'latepoint',
			[
				'label'       => OsSettingsHelper::get_brand_name(),
				'description' => __( 'Appointment scheduling — bookings, customers, services, and staff.', 'latepoint' ),
			]
		);
	}

	/**
	 * Register all abilities from every module.
	 *
	 * Read-only abilities register whenever the master toggle is on.
	 * Mutating abilities require the write toggle; destructive ones
	 * require the delete toggle.
	 */
	public static function register_all(): void {
		$allow_write  = OsSettingsHelper::is_on( 'latepoint_abilities_api_edit' );
		$allow_delete = OsSettingsHelper::is_on( 'latepoint_abilities_api_delete' );

		foreach ( self::$config_modules as $class ) {
			foreach ( $class::get_abilities() as $ability ) {
				if ( $ability->is_destructive() && ! $allow_delete ) {
					continue;
				}
				if ( ! $ability->is_read_only() && ! $ability->is_destructive() && ! $allow_write ) {
					continue;
				}
				wp_register_ability( $ability->get_id(), $ability->to_definition() );
			}
		}
	}
}

LatePointAbilities::init();
