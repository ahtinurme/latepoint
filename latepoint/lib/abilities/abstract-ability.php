<?php
/**
 * Abstract base class for all LatePoint abilities.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractAbility {

	protected string $id;
	protected string $category    = 'latepoint';
	protected string $label       = '';
	protected string $description = '';
	protected string $permission  = 'manage_options';
	protected bool $read_only     = true;
	protected bool $destructive   = false;
	protected bool $idempotent    = false;
	protected string $role        = LATEPOINT_USER_TYPE_ADMIN;
	
	public function __construct() {
		$this->configure();
	}

	/**
	 * Set $id, $label, $description, $permission, $read_only, $destructive.
	 */
	abstract protected function configure(): void;

	abstract public function get_input_schema(): array;

	abstract public function get_output_schema(): array;

	/**
	 * @param array $args
	 * @return array|\WP_Error
	 */
	abstract public function execute( array $args );

	public function get_id(): string {
		return $this->id;
	}

	public function is_read_only(): bool {
		return $this->read_only;
	}

	public function is_destructive(): bool {
		return $this->destructive;
	}

	public function check_permission(): bool {
		// Master gate. In case the ability was registered
		// while the master toggle was on but has since been disabled.
		if ( ! OsSettingsHelper::is_on( 'latepoint_abilities_api' ) ) {
			return false;
		}
		if ( $this->destructive && ! OsSettingsHelper::is_on( 'latepoint_abilities_api_delete' ) ) {
			return false;
		}
		if ( ! $this->read_only && ! $this->destructive
			&& ! OsSettingsHelper::is_on( 'latepoint_abilities_api_edit' ) ) {
			return false;
		}

		// Role gate: when an ability declares a required $role, only that backend user type may
		// invoke it (administrators are always allowed). Leave $role empty ('') to allow any
		// capable backend user. Switch an ability's audience by setting $role in its configure():
		//   $this->role = LATEPOINT_USER_TYPE_ADMIN;  // admin only
		//   $this->role = LATEPOINT_USER_TYPE_AGENT;  // agents (and admins)
		if ( ! empty( $this->role )
			&& OsAuthHelper::get_current_user()->backend_user_type !== $this->role ) {
			return false;
		}

		return OsRolesHelper::can_user( $this->permission );
	}

	public function to_definition(): array {
		return [
			'label'               => $this->label,
			'description'         => $this->description,
			'category'            => $this->category,
			'permission_callback' => [ $this, 'check_permission' ],
			'input_schema'        => self::normalize_schema( $this->get_input_schema() ),
			'output_schema'       => self::normalize_schema( $this->get_output_schema() ),
			'execute_callback'    => [ $this, 'execute' ],
			'meta'                => $this->build_meta(),
		];
	}

	/**
	 * Ensure a JSON Schema serializes to valid JSON.
	 *
	 * An empty PHP array encodes as a JSON array ([]), but JSON Schema requires
	 * `properties` to be an object ({}). A tool advertising `"properties":[]`
	 * is invalid and causes strict AI clients (e.g. Claude Desktop) to reject the
	 * whole connector. Recursively coerce empty `properties` to objects.
	 *
	 * @param mixed $schema
	 * @return mixed
	 */
	protected static function normalize_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		if ( array_key_exists( 'properties', $schema ) ) {
			if ( empty( $schema['properties'] ) ) {
				$schema['properties'] = new \stdClass();
			} else {
				foreach ( $schema['properties'] as $key => $value ) {
					$schema['properties'][ $key ] = self::normalize_schema( $value );
				}
			}
		}
		if ( isset( $schema['items'] ) ) {
			$schema['items'] = self::normalize_schema( $schema['items'] );
		}
		return $schema;
	}

	protected function build_meta(): array {

		// Use the WordPress Abilities API annotation keys (readonly/destructive/
		// idempotent/openWorldHint), matching SureForms, so the MCP Adapter emits
		// concrete boolean values. Supplying only the *Hint variants leaves these
		// base keys as null in the tool output, which strict AI clients (e.g. Claude
		// Desktop) reject — causing the whole connector to show "no tools available".
		$annotations = [
			'readonly'      => $this->read_only,
			'destructive'   => false,
			'idempotent'    => $this->idempotent,
			'openWorldHint' => false,
			'priority'      => $this->read_only ? 1.0 : 2.0 ,
		];

		// Destructive overrides everything.
		if ( $this->destructive ) {
			$annotations['readonly']    = false;
			$annotations['destructive'] = true;
			$annotations['priority']    = 3.0;
		}

		$meta = [
			'annotations'  => $annotations,
			'show_in_rest' => true,
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];

		return $meta;
	}

	/**
	 * Per-record ownership/scope check. Returns WP_Error (403) when the current
	 * user is not allowed to act on this specific record. Admins always pass.
	 *
	 * @param OsModel $model
	 * @param string  $action  one of 'view' | 'edit' | 'delete'
	 * @return true|\WP_Error
	 */
	protected function authorize_record( OsModel $model, string $action ) {
		if ( ! OsRolesHelper::can_user_make_action_on_model_record( $model, $action ) ) {
			return new WP_Error(
				'forbidden',
				__( 'You are not allowed to access this record.', 'latepoint' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	protected static function pagination(): array {
		return [
			'page'     => [
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'description' => __( 'Page number.', 'latepoint' ),
			],
			'per_page' => [
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
				'description' => __( 'Items per page.', 'latepoint' ),
			],
		];
	}
}
