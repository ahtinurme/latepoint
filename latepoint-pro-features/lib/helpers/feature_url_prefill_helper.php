<?php
/**
 * Feature: Pre-fill customer contact fields from URL parameters.
 *
 * When the "enable_url_prefill" setting is on, query-string parameters are
 * forwarded through the existing presets pipeline (JS → params.presets.url_params
 * → PHP latepoint_set_presets) and then seeded onto the customer object just
 * before the contact step renders. Values are only applied to *empty* fields so
 * that logged-in customers and values the user edits always win.
 *
 * Supported URL parameters (1:1 mapping — no admin mapping UI in this version):
 *   first_name, last_name, email, phone, notes
 *   <custom-field-id>  (public customer custom fields, e.g. membership_id=12345)
 *
 * @package LatePoint Pro Features
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OsFeatureUrlPrefillHelper
 */
class OsFeatureUrlPrefillHelper {

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Returns true when the feature is enabled by the admin.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return OsSettingsHelper::is_on( 'enable_url_prefill' );
	}

	/**
	 * Returns the standard customer field keys supported for pre-fill.
	 *
	 * @return string[]
	 */
	private static function standard_fields(): array {
		return array( 'first_name', 'last_name', 'email', 'phone', 'notes' );
	}

	/**
	 * Sanitize a raw string value per the field key.
	 *
	 * @param string $field  Field key (first_name|last_name|email|phone|notes).
	 * @param string $value  Raw, untrusted value.
	 *
	 * @return string Sanitized value, or empty string when the value fails
	 *                field-specific validation.
	 */
	private static function sanitize_field_value( string $field, string $value ): string {
		switch ( $field ) {
			case 'email':
				$sanitized = sanitize_email( $value );
				return OsUtilHelper::is_valid_email( $sanitized ) ? $sanitized : '';

			case 'phone':
				return OsUtilHelper::sanitize_phone_number( $value );

			case 'notes':
				return sanitize_textarea_field( $value );

			default: // first_name, last_name
				return sanitize_text_field( $value );
		}
	}

	// ------------------------------------------------------------------
	// Hook: latepoint_get_default_presets
	// ------------------------------------------------------------------

	/**
	 * Register the prefill preset keys so they survive the default-presets
	 * whitelist check in OsStepsHelper::set_default_presets().
	 *
	 * @param array $presets Default presets array.
	 *
	 * @return array
	 */
	public static function register_default_presets( array $presets ): array {
		$presets['prefill_first_name']    = false;
		$presets['prefill_last_name']     = false;
		$presets['prefill_email']         = false;
		$presets['prefill_phone']         = false;
		$presets['prefill_notes']         = false;
		$presets['prefill_custom_fields'] = false;
		return $presets;
	}

	// ------------------------------------------------------------------
	// Hook: latepoint_set_presets
	// ------------------------------------------------------------------

	/**
	 * Compute and store prefill presets from URL parameters.
	 *
	 * Called on latepoint_set_presets (priority 10). On the *first* step the
	 * raw URL params arrive in $raw['url_params'] (injected by the Free JS).
	 * On subsequent steps the already-sanitized values are carried forward as
	 * $raw['prefill_<field>'] hidden fields and decoded here.
	 *
	 * @param array $presets Current presets array (keys already registered via
	 *                       register_default_presets).
	 * @param array $raw     The raw $presets input passed to set_presets().
	 *
	 * @return array
	 */
	public static function set_prefill_presets( array $presets, array $raw ): array {
		$default_fields = OsSettingsHelper::get_default_fields_for_customer();

		// --- Standard fields ---
		foreach ( self::standard_fields() as $field ) {
			// Source A: first AJAX call — value comes from window.location.search.
			$value = '';
			if ( ! empty( $raw['url_params'][ $field ] ) ) {
				$value = self::sanitize_field_value( $field, (string) $raw['url_params'][ $field ] );
			}

			// Source B: subsequent steps — value carried as a hidden preset field.
			if ( empty( $value ) && ! empty( $raw[ 'prefill_' . $field ] ) ) {
				// Already sanitized on intake; re-sanitize defensively.
				$value = self::sanitize_field_value( $field, (string) $raw[ 'prefill_' . $field ] );
			}

			// Respect the admin field-active setting — ignore param if field is disabled.
			if ( ! empty( $value ) && ( empty( $default_fields[ $field ] ) || ! $default_fields[ $field ]['active'] ) ) {
				$value = '';
			}

			if ( ! empty( $value ) ) {
				$presets[ 'prefill_' . $field ] = $value;
			}
		}

		// --- Customer custom fields ---
		$custom_fields_arr = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );
		if ( ! empty( $custom_fields_arr ) ) {
			$collected = array();

			// Source A: first call.
			if ( ! empty( $raw['url_params'] ) && is_array( $raw['url_params'] ) ) {
				$known_ids = array_column( $custom_fields_arr, 'id' );
				foreach ( $raw['url_params'] as $param_key => $param_value ) {
					if ( in_array( $param_key, $known_ids, true ) ) {
						$collected[ $param_key ] = sanitize_text_field( (string) $param_value );
					}
				}
			}

			// Source B: subsequent steps — carried as JSON.
			if ( empty( $collected ) && ! empty( $raw['prefill_custom_fields'] ) ) {
				$decoded = json_decode( wp_unslash( (string) $raw['prefill_custom_fields'] ), true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $id => $val ) {
						$collected[ sanitize_key( $id ) ] = sanitize_text_field( (string) $val );
					}
				}
			}

			if ( ! empty( $collected ) ) {
				$presets['prefill_custom_fields'] = wp_json_encode( $collected );
			}
		}

		return $presets;
	}

	// ------------------------------------------------------------------
	// Hook: latepoint_booking_form_params_presets_after
	// ------------------------------------------------------------------

	/**
	 * Carry prefill presets as hidden fields across booking-form steps.
	 *
	 * These echo hidden <input> tags inside the existing .latepoint-presets
	 * wrapper, mirroring how the native presets (e.g. selected_service) are
	 * round-tripped through the multi-step AJAX flow.
	 *
	 * @param OsBookingModel $booking          Current booking object.
	 * @param array          $restrictions     Restrictions array.
	 * @param array          $presets          Presets array.
	 * @param string         $current_step_code Current step code.
	 * @param string         $add_string_to_id  Random suffix for field IDs.
	 *
	 * @return void
	 */
	public static function carry_preset_fields( $booking, array $restrictions, array $presets, string $current_step_code, string $add_string_to_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$active_presets = OsStepsHelper::$presets;

		foreach ( self::standard_fields() as $field ) {
			$key = 'prefill_' . $field;
			if ( ! empty( $active_presets[ $key ] ) ) {
				echo OsFormHelper::hidden_field( 'presets[' . $key . ']', $active_presets[ $key ], array( 'skip_id' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( ! empty( $active_presets['prefill_custom_fields'] ) ) {
			echo OsFormHelper::hidden_field( 'presets[prefill_custom_fields]', $active_presets['prefill_custom_fields'], array( 'skip_id' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	// ------------------------------------------------------------------
	// Hook: latepoint_set_customer_object
	// ------------------------------------------------------------------

	/**
	 * Seed empty customer fields from the prefill presets.
	 *
	 * Runs after the customer object has been built from submitted form data
	 * or loaded from the session. Only sets properties that are currently
	 * *empty* so that a logged-in customer's data and any value the user has
	 * already typed always take precedence.
	 *
	 * @param OsCustomerModel $customer       Customer object.
	 * @param array           $customer_params Raw customer params used to build the object.
	 *
	 * @return OsCustomerModel
	 */
	public static function prefill_customer_object( OsCustomerModel $customer, array $customer_params ): OsCustomerModel { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$presets        = OsStepsHelper::$presets;
		$default_fields = OsSettingsHelper::get_default_fields_for_customer();

		// --- Standard fields ---
		foreach ( self::standard_fields() as $field ) {
			$preset_key = 'prefill_' . $field;
			if ( empty( $presets[ $preset_key ] ) ) {
				continue;
			}
			// Skip if the field is disabled in admin settings.
			if ( empty( $default_fields[ $field ] ) || ! $default_fields[ $field ]['active'] ) {
				continue;
			}
			// Only set if the customer object doesn't already have a value.
			if ( empty( $customer->$field ) ) {
				$customer->$field = $presets[ $preset_key ];
			}
		}

		// --- Customer custom fields ---
		if ( ! empty( $presets['prefill_custom_fields'] ) ) {
			$decoded = json_decode( wp_unslash( (string) $presets['prefill_custom_fields'] ), true );
			if ( is_array( $decoded ) ) {
				if ( ! isset( $customer->custom_fields ) || ! is_array( $customer->custom_fields ) ) {
					$customer->custom_fields = array();
				}
				foreach ( $decoded as $id => $value ) {
					if ( ! isset( $customer->custom_fields[ $id ] ) ) {
						$customer->custom_fields[ $id ] = $value;
					}
				}
			}
		}

		return $customer;
	}

	// ------------------------------------------------------------------
	// Hook: latepoint_settings_general_customer_after
	// ------------------------------------------------------------------

	/**
	 * Output the admin setting toggle for this feature.
	 *
	 * Hooks onto latepoint_settings_general_customer_after and renders a
	 * toggler field using the standard settings[...] naming convention so
	 * the core settings controller saves it automatically — no custom save
	 * handler is needed.
	 *
	 * @return void
	 */
	public static function output_settings(): void {
		?>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'URL Pre-fill', 'latepoint-pro-features' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<?php
				echo OsFormHelper::toggler_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'settings[enable_url_prefill]',
					__( 'Pre-fill customer fields from URL parameters', 'latepoint-pro-features' ),
					OsSettingsHelper::is_on( 'enable_url_prefill' ),
					false,
					false,
					array(
						'sub_label' => __( 'When enabled, standard customer fields (first_name, last_name, email, phone, notes) and public custom fields can be pre-populated by adding matching URL query parameters to your booking page URL. Example: /book?first_name=John&email=john@example.com', 'latepoint-pro-features' ),
					)
				);
				?>
			</div>
		</div>
		<?php
	}
}
