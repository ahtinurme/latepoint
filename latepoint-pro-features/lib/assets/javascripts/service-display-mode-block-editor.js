/* global wp */
( function () {
	'use strict';

	var el             = wp.element.createElement;
	var __             = wp.i18n.__;
	var SelectControl  = wp.components.SelectControl;
	var PanelBody      = wp.components.PanelBody;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var Fragment       = wp.element.Fragment;
	var addFilter      = wp.hooks.addFilter;

	var SUPPORTED_BLOCKS = [ 'latepoint/book-button', 'latepoint/book-form' ];

	var SERVICE_DISPLAY_OPTIONS = [
		{ label: __( 'Show All Services', 'latepoint-pro-features' ), value: '' },
		{ label: __( 'Show Only Individual Services', 'latepoint-pro-features' ), value: 'services_only' },
		{ label: __( 'Show Only Bundled Services', 'latepoint-pro-features' ), value: 'bundles_only' },
	];

	/**
	 * Higher-order component that wraps BlockEdit and appends the
	 * Service Display inspector panel to LatePoint booking blocks.
	 */
	function withServiceDisplayControl( BlockEdit ) {
		return function ( props ) {
			if ( SUPPORTED_BLOCKS.indexOf( props.name ) === -1 ) {
				return el( BlockEdit, props );
			}

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Service Display', 'latepoint-pro-features' ),
							initialOpen: false,
						},
						el( SelectControl, {
							label: __( 'Service Display Mode', 'latepoint-pro-features' ),
							value: props.attributes.service_display_mode || '',
							options: SERVICE_DISPLAY_OPTIONS,
							onChange: function ( value ) {
								props.setAttributes( { service_display_mode: value } );
							},
						} )
					)
				)
			);
		};
	}

	addFilter(
		'editor.BlockEdit',
		'latepoint-pro/service-display-mode-control',
		withServiceDisplayControl
	);
} )();
