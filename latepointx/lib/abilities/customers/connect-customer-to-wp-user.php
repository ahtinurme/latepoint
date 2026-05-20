<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityConnectCustomerToWpUser extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/connect-customer-to-wp-user';
		$this->label       = __( 'Connect customer to WP user', 'latepoint' );
		$this->description = __( 'Links a LatePoint customer record to an existing WordPress user account by their user ID.', 'latepoint' );
		$this->permission  = 'customer__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'customer_id' => [
					'type'        => 'integer',
					'description' => __( 'LatePoint customer ID.', 'latepoint' ),
				],
				'wp_user_id'  => [
					'type'        => 'integer',
					'description' => __( 'WordPress user ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'customer_id', 'wp_user_id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->customer_output_schema();
	}

	public function execute( array $args ) {
		$customer = new OsCustomerModel( (int) $args['customer_id'] );
		if ( $customer->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$wp_user_id  = (int) $args['wp_user_id'];
		$target_user = get_userdata( $wp_user_id );
		if ( ! $target_user ) {
			return new WP_Error( 'wp_user_not_found', __( 'WordPress user not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		// Only allow linking to non-privileged WP accounts using an allowlist of roles.
		$allowed_roles = [ LATEPOINT_WP_CUSTOMER_ROLE, 'subscriber', 'customer' ];
		$user_roles    = (array) $target_user->roles;
		if ( empty( $user_roles ) || ! empty( array_diff( $user_roles, $allowed_roles ) ) ) {
			return new WP_Error(
				'privileged_user',
				__( 'Cannot link a customer to a privileged WordPress account.', 'latepoint' ),
				[ 'status' => 403 ]
			);
		}

		$customer->wordpress_user_id = $wp_user_id;
		if ( ! $customer->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to link customer to WordPress user.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $customer->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_customer( new OsCustomerModel( $customer->id ) );
	}
}
