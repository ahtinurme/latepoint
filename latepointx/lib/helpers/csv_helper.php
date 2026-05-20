<?php

class OsCSVHelper {
	/**
	 * Escape CSV formula injection by prefixing with single quote
	 *
	 * Prevents CSV formula injection attacks where formulas starting with =, +, -, @, tab, or pipe
	 * could execute when CSV is opened in Excel/Google Sheets/Apple Numbers.
	 * Uses single quote prefix which is recognized as a text marker across all major spreadsheet apps.
	 *
	 * @since 5.1.0 Security fix for CSV formula injection
	 * @param mixed $value Value to escape
	 * @return mixed Escaped value
	 */
	public static function escape_csv_formula( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) === 0 ) {
			return $value;
		}

		$first_char = $value[0];
		// OWASP-recommended dangerous prefixes: =, +, -, @, tab, pipe
		$dangerous_chars = [ '=', '+', '-', '@', "\t", '|' ];

		if ( in_array( $first_char, $dangerous_chars, true ) ) {
			return "'" . $value;  // Prepend single quote to neutralize formula
		}

		// Strip embedded newlines to prevent multi-row injection
		if ( strpos( $value, "\r" ) !== false || strpos( $value, "\n" ) !== false ) {
			return str_replace( [ "\r", "\n" ], '', $value );
		}

		return $value;
	}

	public static function array_to_csv( $data ) {
		$output = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		foreach ( $data as $row ) {
			// Escape each cell to prevent CSV formula injection
			$escaped_row = array_map( [ self::class, 'escape_csv_formula' ], $row );
			fputcsv( $output, $escaped_row );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}


	public static function get_import_dir( bool $create = true ): string {
		$wp_upload_dir = wp_upload_dir( null, $create );
		if ( $wp_upload_dir['error'] ) {
			throw new \Exception( esc_html( $wp_upload_dir['error'] ) );
		}

		$upload_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'latepoint';
		if ( $create ) {
			if ( ! file_exists( $upload_dir ) ) {
				wp_mkdir_p( $upload_dir );
			}
		}
		return $upload_dir;
	}

	public static function upload_csv_file( $files, $file_name ) {
		if ( empty( $files[ $file_name ] ) ) {
			throw new \Exception( 'File not selected' );
		}

		$file = $files[ $file_name ];

		// Security: Validate file before upload (defense-in-depth)
		if ( ! self::validate_csv_upload( $file ) ) {
			throw new \Exception( 'Invalid CSV file format' );
		}

		$upload_dir = OsCsvHelper::get_import_dir();
		$tmp_name   = uniqid( 'latepoint_customers_csv_' ) . '.csv';
		$filepath   = $upload_dir . '/' . $tmp_name;

		if ( ! move_uploaded_file( $file['tmp_name'][0], $filepath ) ) {
			throw new \Exception( 'Error uploading file' );
		}
		set_transient( 'csv_import_file_' . OsWpUserHelper::get_current_user_id(), $filepath, 3600 );
		return $filepath;
	}

	/**
	 * Validates CSV file upload using multiple layers of security checks.
	 * Defense-in-depth approach: extension check, MIME type check, and structure validation.
	 *
	 * @param array $file Uploaded file array from $_FILES
	 * @return bool True if file is valid CSV, false otherwise
	 */
	public static function validate_csv_upload( $file ): bool {
		$file_name = is_array( $file['name'] ) ? $file['name'][0] : $file['name'];
		$tmp_name  = is_array( $file['tmp_name'] ) ? $file['tmp_name'][0] : $file['tmp_name'];

		if ( ! file_exists( $tmp_name ) ) {
			return false;
		}

		// Step 1: Validate extension and MIME type using WordPress
		$allowed_mimes = [
			'csv' => 'text/csv',
		];
		$validated     = wp_check_filetype_and_ext( $tmp_name, $file_name, $allowed_mimes );
		if ( ! $validated['ext'] || ! $validated['type'] ) {
			return false;
		}

		// Step 2: Validate CSV structure by attempting to read first line
		$handle = fopen( $tmp_name, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( $handle === false ) {
			return false;
		}

		$first_line = fgetcsv( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! is_array( $first_line ) || empty( $first_line ) ) {
			return false;
		}

		return true;
	}


	public static function is_valid_csv( $file_path ): bool {
		$valid_filetypes = [
			'csv' => 'text/csv',
			'txt' => 'text/plain',
		];

		$filetype = wp_check_filetype( $file_path, $valid_filetypes );

		if ( in_array( $filetype['type'], $valid_filetypes, true ) ) {
			return true;
		}

		return false;
	}

	public static function get_csv_data( $file_path, $limit = false ) {
		if ( ! file_exists( $file_path ) ) {
			throw new \Exception( 'File does not exist' );
		}

		if ( ! OsCSVHelper::is_valid_csv( $file_path ) ) {
			throw new \Exception( 'Invalid file format' );
		}

		$data = [];
		$i    = 0;
		if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				$data[] = $row;
				$i++;
				if ( $limit && $i >= $limit ) {
					break;
				}
			}
			fclose( $handle );
		} else {
			throw new \Exception( 'Error reading file' );
		}
		return $data;
	}
}
