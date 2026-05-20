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

	public function check_permission(): bool {
		return OsRolesHelper::can_user( $this->permission );
	}

	public function to_definition(): array {
		return [
			'label'               => $this->label,
			'description'         => $this->description,
			'category'            => $this->category,
			'permission_callback' => [ $this, 'check_permission' ],
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'execute_callback'    => [ $this, 'execute' ],
			'meta'                => $this->build_meta(),
		];
	}

	protected function build_meta(): array {

		// Default annotations, normally read-only
		$annotations = [
			'readOnlyHint'    => $this->read_only,
			'idempotentHint'  => $this->idempotent,
			'destructiveHint' => false,
			'priority'        => $this->read_only ? 1.0 : 2.0,
		];

		// Destructive overrides everything.
		if ( $this->destructive ) {
			$annotations['readOnlyHint']    = false;
			$annotations['destructiveHint'] = true;
			$annotations['priority']        = 3.0;
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
