<?php

class OsMessageModel extends OsModel {
	public $id;
	public $content;
	public $content_type;
	public $author_id;
	public $booking_id;
	public $author_type;
	public $is_hidden;
	public $is_read;
	public $created_at;
	public $updated_at;

	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_MESSAGES;
		$this->nice_names = [ 'content' => __( 'Content', 'latepoint-pro-features' ) ];

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}

	public function filter_allowed_records(): OsModel {
		if ( ! OsRolesHelper::are_all_records_allowed() ) {
			// join bookings table to filter allowed transactions
			$this->join( LATEPOINT_TABLE_BOOKINGS, [ 'id' => $this->table_name . '.booking_id' ] );
			if ( ! OsRolesHelper::are_all_records_allowed( 'agent' ) ) {
				$this->select( LATEPOINT_TABLE_BOOKINGS . '.agent_id' );
				$this->filter_where_conditions( [ LATEPOINT_TABLE_BOOKINGS . '.agent_id' => OsRolesHelper::get_allowed_records( 'agent' ) ] );
			}
			if ( ! OsRolesHelper::are_all_records_allowed( 'location' ) ) {
				$this->select( LATEPOINT_TABLE_BOOKINGS . '.location_id' );
				$this->filter_where_conditions( [ LATEPOINT_TABLE_BOOKINGS . '.location_id' => OsRolesHelper::get_allowed_records( 'location' ) ] );
			}
			if ( ! OsRolesHelper::are_all_records_allowed( 'service' ) ) {
				$this->select( LATEPOINT_TABLE_BOOKINGS . '.service_id' );
				$this->filter_where_conditions( [ LATEPOINT_TABLE_BOOKINGS . '.service_id' => OsRolesHelper::get_allowed_records( 'service' ) ] );
			}
		}
		return $this;
	}


	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = array(
			'id',
			'content',
			'content_type',
			'author_id',
			'booking_id',
			'author_type',
			'is_hidden',
			'is_read',
			'created_at',
			'updated_at',
		);
		return $allowed_params;
	}
  
	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = array(
			'id',
			'content',
			'content_type',
			'author_id',
			'booking_id',
			'author_type',
			'is_hidden',
			'is_read',
			'created_at',
			'updated_at',
		);
		return $params_to_save;
	}


	protected function get_booking() {
		if ( $this->booking_id ) {
			if ( ! isset( $this->booking ) || ( isset( $this->booking ) && ( $this->booking->id != $this->booking_id ) ) ) {
				$this->booking = new OsBookingModel( $this->booking_id );
			}
		} else {
			$this->booking = new OsBookingModel();
		}
		return $this->booking;
	}

	protected function before_create() {
		if ( empty( $this->content_type ) ) {
			$this->content_type = LATEPOINT_MESSAGE_CONTENT_TYPE_TEXT;
		}
		if ( empty( $this->is_hidden ) ) {
			$this->is_hidden = false;
		}
		if ( empty( $this->is_read ) ) {
			$this->is_read = false;
		}
	}


	public function get_author_avatar() {
		$avatar_url = LATEPOINT_DEFAULT_AVATAR_URL;
		switch ( $this->author_type ) {
			case 'agent':
				$agent_model = new OsAgentModel( $this->author_id );
				if ( $agent_model->id ) {
					$avatar_url = $agent_model->get_avatar_url();
				}
				break;
			case 'admin':
				$wp_user = OsAuthHelper::get_logged_in_wp_user();
				if ( $wp_user ) {
					$avatar_url = get_avatar_url( $wp_user->user_email );
				}
				break;
			case 'customer':
				$customer_model = new OsCustomerModel( $this->author_id );
				if ( $customer_model->id ) {
					$avatar_url = $customer_model->get_avatar_url();
				}
				break;
		}
		return $avatar_url;
	}


	protected function properties_to_validate() {
		$validations = array(
			'content' => array( 'presence' ),
		);
		return $validations;
	}
}
