<?php

class OsCustomerImportHelper {

	/**
	 * Get an array of fields for mapping during customer import.
	 * @return array
	 */
	public static function get_import_fields(): array {
		$import_fields = [
			''            => 'Do not import',
			'first_name'  => __( 'First Name', 'latepoint' ),
			'last_name'   => __( 'Last Name', 'latepoint' ),
			'email'       => __( 'Email', 'latepoint' ),
			'phone'       => __( 'Phone Number', 'latepoint' ),
			'notes'       => __( 'Notes', 'latepoint' ),
			'admin_notes' => __( 'Admin Notes', 'latepoint' ),
		];

		/**
		 * Returns an array of fields for mapping during customer import.
		 *
		 * @since 5.2.0
		 * @hook latepoint_customer_import_fields
		 *
		 * @param {array} $import_fields Array of fields for mapping during customer import
		 *
		 * @returns {array} array of fields for mapping during customer import
		 */
		return apply_filters( 'latepoint_customer_import_fields', $import_fields );
	}


	/**
	 * Check if a customer can be imported by email.
	 * @param $email
	 * @return array|true[]
	 */
	public static function check_import_client_by_email( $email = '' ): array {
		if ( empty( $email ) || ! OsUtilHelper::is_valid_email( $email ) ) {
			return [
				'status'  => false,
				'message' => esc_html__( 'Invalid email address: ' . $email, 'latepoint' ),
			];
		}
		$customer = new OsCustomerModel();
		$customer = $customer->where( [ 'email' => $email ] )->set_limit( 1 )->get_results_as_models();
		if ( $customer ) {
			return [
				'status'  => false,
				'message' => esc_html__( 'Customer with email already exists: ' . $email, 'latepoint' ),
			];
		}
		return [ 'status' => true ];
	}


	/**
	 * Validate mapping between csv and fields from db
	 * @param array $column_mapping
	 * @return bool
	 */
	public static function validate_import_mapping( array $column_mapping ): bool {
		if ( empty( $column_mapping ) ) {
			return false;
		}

		$email_field_index = array_search( 'email', $column_mapping );
		return $email_field_index !== false;
	}


	/**
	 * Get the temporary file path for the uploaded CSV file.
	 * @return string
	 * @throws Exception
	 */
	public static function get_import_tmp_filepath(): string {
		$file_path = get_transient( 'csv_import_file_' . OsWpUserHelper::get_current_user_id() );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			throw new Exception( 'Import file not found or expired. Please upload the file again.' );
		}

		return $file_path;
	}


	/**
	 * Check CSV to find number of existing customers
	 * @param array $csv_data
	 * @param array $column_mapping
	 *
	 * @return array
	 */
	public static function validate_csv_data( array $csv_data, array $column_mapping ): array {
		$email_field_index = array_search( 'email', $column_mapping );
		$conflicts         = [];
		$importableCount   = 0;

		foreach ( $csv_data as $row_index => $row_data ) {
			// Skip header row
			if ( $row_index === 0 ) {
				continue;
			}

			$email             = $row_data[ $email_field_index ] ?? '';
			$validation_result = OsCustomerImportHelper::validate_customer_email( $email );

			if ( ! $validation_result['status'] ) {
				$conflicts[ $validation_result['type'] ][] = $validation_result['data'];
			} else {
				$importableCount++;
			}
		}

		return [
			'conflicts'        => $conflicts,
			'importable_count' => $importableCount,
		];
	}


	/**
	 * Validate customer email
	 * @param string $email
	 * @return array
	 */
	public static function validate_customer_email( string $email ): array {
		if ( empty( $email ) || ! OsUtilHelper::is_valid_email( $email ) ) {
			return [
				'status' => false,
				'type'   => 'invalid',
				'data'   => $email,
			];
		}

		$existingCustomer = new OsCustomerModel();
		$existingCustomer = $existingCustomer->where( [ 'email' => $email ] )->set_limit( 1 )->get_results_as_models();
		if ( $existingCustomer ) {
			return [
				'status' => false,
				'type'   => 'duplicate',
				'data'   => $email,
			];
		}

		return [ 'status' => true ];
	}

	/**
	 * Import customers from CSV
	 * @param array $csv_data
	 * @param array $column_mapping
	 * @param bool $update_existing
	 *
	 * @return array
	 */
	public static function import_customers( array $csv_data, array $column_mapping, bool $update_existing = false ): array {
		$emailFieldIndex = array_search( 'email', $column_mapping );
		$skippedCount    = 0;
		$updatedCount    = 0;

		foreach ( $csv_data as $rowIndex => $rowData ) {
			// Skip header row
			if ( $rowIndex === 0 ) {
				continue;
			}

			$email = $rowData[ $emailFieldIndex ] ?? '';

			if ( empty( $email ) || ! OsUtilHelper::is_valid_email( $email ) ) {
				$skippedCount++;
				continue;
			}

			$customer = new OsCustomerModel();
			$customer = $customer->where( [ 'email' => $email ] )->set_limit( 1 )->get_results_as_models();

			// Skip if customer exists and update is not allowed
			if ( $customer && ! $update_existing ) {
				$skippedCount++;
				continue;
			}

			// Create new customer if not found
			if ( ! $customer ) {
				$customer = new OsCustomerModel();
			}

			// Prepare save data
			$save_data = [];
			foreach ( $rowData as $column_index => $field_value ) {
				if ( ! empty( $column_mapping[ $column_index ] ) ) {
					$save_data[ $column_mapping[ $column_index ] ] = $field_value;
				}
			}
			$customer->set_data( $save_data, 'public' );

			if ( $customer->save() ) {
				$updatedCount++;
				do_action( 'latepoint_customer_imported', $customer, $rowData, $column_mapping );
			} else {
				$skippedCount++;
			}
		}

		return [
			'skipped_count' => $skippedCount,
			'updated_count' => $updatedCount,
		];
	}

	/**
	 * Delete temp file
	 * @return void
	 */
	public static function cleanup_stored_file(): void {
		$file_name = 'csv_import_file_' . OsWpUserHelper::get_current_user_id();
		$file_path = get_transient( $file_name );

		if ( $file_path && file_exists( $file_path ) ) {
			unlink( $file_path );
			delete_transient( $file_name );
		}
	}
}
