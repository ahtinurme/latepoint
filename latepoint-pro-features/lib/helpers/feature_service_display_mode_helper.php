<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OsFeatureServiceDisplayModeHelper {

	private const ALLOWED_MODES = [ 'all', 'services_only', 'bundles_only' ];

	/**
	 * Adds service_display_mode to the default booking attribute list so it is
	 * emitted as a data-* attribute on booking button/form shortcodes.
	 *
	 * @param array $atts Default booking attributes.
	 * @return array
	 */
	public static function add_default_booking_att( array $atts ): array {
		$atts['service_display_mode'] = false;
		return $atts;
	}

	/**
	 * Registers service_display_mode as a recognised restriction key with a
	 * default value of 'all'.
	 *
	 * @param array $restrictions Default restrictions array.
	 * @return array
	 */
	public static function add_default_restriction( array $restrictions ): array {
		$restrictions['service_display_mode'] = 'all';
		return $restrictions;
	}

	/**
	 * Copies service_display_mode from the incoming raw restrictions into the
	 * active restrictions array used by the booking form steps.
	 *
	 * @param array $restrictions   Already-built restrictions array.
	 * @param array $raw            Raw restrictions coming from the request.
	 * @return array
	 */
	public static function set_restriction( array $restrictions, array $raw ): array {
		if ( isset( $raw['service_display_mode'] ) && in_array( $raw['service_display_mode'], self::ALLOWED_MODES, true ) ) {
			$restrictions['service_display_mode'] = sanitize_text_field( $raw['service_display_mode'] );
		}
		return $restrictions;
	}

	/**
	 * Filters the services array for the booking form services step.
	 * When service_display_mode is 'bundles_only', all individual services are
	 * hidden so only bundle tiles remain.
	 *
	 * @param OsServiceModel[] $services     Services to display.
	 * @param OsBundleModel[]  $bundles      Bundles to display (unused here).
	 * @param array            $restrictions Active booking-form restrictions.
	 * @return OsServiceModel[]
	 */
	public static function filter_step_services( array $services, $bundles, array $restrictions ): array {
		$mode = $restrictions['service_display_mode'] ?? 'all';
		if ( 'bundles_only' === $mode ) {
			return [];
		}
		return $services;
	}

	/**
	 * Filters the bundles array for the booking form services step.
	 * When service_display_mode is 'services_only', all bundle tiles are
	 * hidden so only individual service tiles remain.
	 *
	 * @param OsBundleModel[]  $bundles      Bundles to display.
	 * @param OsServiceModel[] $services     Services to display (unused here).
	 * @param array            $restrictions Active booking-form restrictions.
	 * @return OsBundleModel[]
	 */
	public static function filter_step_bundles( array $bundles, $services, array $restrictions ): array {
		$mode = $restrictions['service_display_mode'] ?? 'all';
		if ( 'services_only' === $mode ) {
			return [];
		}
		return $bundles;
	}

	/**
	 * Emits a hidden form field for restrictions[service_display_mode] so the
	 * value persists across AJAX step transitions.
	 *
	 * Hooked on 'latepoint_booking_form_params'.
	 *
	 * @param OsBookingModel $booking          The booking object (unused).
	 * @param array          $restrictions     Active restrictions array.
	 * @return void
	 */
	public static function output_form_param( $booking, array $restrictions ): void {
		$mode = $restrictions['service_display_mode'] ?? 'all';
		if ( ! in_array( $mode, self::ALLOWED_MODES, true ) ) {
			$mode = 'all';
		}
		echo OsFormHelper::hidden_field( 'restrictions[service_display_mode]', esc_attr( $mode ), [ 'skip_id' => true ] );
	}

	/**
	 * Returns true when the bundle folder wrapper should be skipped so
	 * bundle tiles render directly without the "Bundle & Save" header.
	 *
	 * @param bool  $skip         Current skip value.
	 * @param array $restrictions Active booking-form restrictions.
	 * @return bool
	 */
	public static function skip_bundles_folder_wrapper( bool $skip, array $restrictions ): bool {
		return 'bundles_only' === ( $restrictions['service_display_mode'] ?? 'all' );
	}

	/**
	 * Adds service_display_mode to the whitelist of params forwarded from
	 * Elementor and Bricks widget settings to the booking shortcode.
	 *
	 * @param string[] $allowed_params Existing allowed param names.
	 * @param string   $widget_type    Widget type identifier (unused).
	 * @return string[]
	 */
	public static function add_widget_allowed_param( array $allowed_params, string $widget_type ): array {
		$allowed_params[] = 'service_display_mode';
		return $allowed_params;
	}

	/**
	 * Injects a "Service Display" section with a SelectControl into the
	 * Elementor booking widget editor panel.
	 *
	 * @param \Elementor\Widget_Base $element The Elementor widget element.
	 * @param array                  $args    Section arguments (unused).
	 * @return void
	 */
	public static function add_elementor_control( $element, array $args ): void {
		$element->start_controls_section(
			'section_service_display_mode',
			[
				'label' => esc_html__( 'Service Display', 'latepoint-pro-features' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$element->add_control(
			'service_display_mode',
			[
				'label'   => esc_html__( 'Service Display Mode', 'latepoint-pro-features' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''              => esc_html__( 'Show All Services', 'latepoint-pro-features' ),
					'services_only' => esc_html__( 'Show Only Individual Services', 'latepoint-pro-features' ),
					'bundles_only'  => esc_html__( 'Show Only Bundled Services', 'latepoint-pro-features' ),
				],
			]
		);

		$element->end_controls_section();
	}
}
