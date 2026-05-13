class LatepointAppleCalendarAdminAddon {
	// Init
	constructor() {
		this.init();
	}

	remove_first_synced_booking_with_apple() {
		const $trigger = jQuery(
			'.os-booking-tiny-box.is-synced:first .os-booking-sync-apple-trigger'
		);
		const $removeBtn = jQuery( '.remove-all-bookings-from-apple-trigger' );

		if ( ! $trigger.length || $removeBtn.hasClass( 'stop-removing' ) ) {
			$removeBtn
				.removeClass( 'os-removing' )
				.removeClass( 'stop-removing' )
				.find( 'span' )
				.text( $removeBtn.data( 'label-remove' ) );
			return false;
		}

		const route = $trigger.data( 'os-remove-action' );
		const params = $trigger.data( 'os-params' );
		const data = {
			action: latepoint_helper.route_action,
			route_name: route,
			params,
			layout: 'none',
			return_format: 'json',
		};

		$trigger.addClass( 'os-loading' );
		jQuery.ajax( {
			type: 'post',
			dataType: 'json',
			url: latepoint_timestamped_ajaxurl(),
			data,
			success: ( response ) => {
				if ( response.status === 'success' ) {
					$trigger
						.closest( '.os-booking-tiny-box' )
						.removeClass( 'is-synced' )
						.addClass( 'not-synced' );
					$trigger.removeClass( 'os-loading' );

					// Update progress bar
					const $wrapper = $trigger
						.closest( '.syncing-calendar-wrapper' )
						.find( '.os-sync-stats-and-progress-w' );
					const synced_total =
						parseInt(
							$wrapper.find( '.os-sync-value span' ).text()
						) - 1;
					$wrapper.find( '.os-sync-value span' ).text( synced_total );
					$wrapper
						.find( '.os-sync-progress' )
						.data( 'value', synced_total );
					const chart_total = $wrapper
						.find( '.os-sync-progress' )
						.data( 'total' );
					if ( chart_total > 0 ) {
						const percent = Math.min(
							Math.round( ( synced_total / chart_total ) * 100 ),
							100
						);
						$wrapper
							.find( '.os-sync-progress-bar' )
							.css( 'width', `${ percent }%` );
					}

					this.remove_first_synced_booking_with_apple();
				}
			},
		} );
	}

	sync_next_booking_with_apple() {
		const $trigger = jQuery(
			'.os-booking-tiny-box.not-synced:first .os-booking-sync-apple-trigger'
		);
		const $syncBtn = jQuery( '.sync-all-bookings-to-apple-trigger' );

		if ( ! $trigger.length || $syncBtn.hasClass( 'stop-syncing' ) ) {
			$syncBtn
				.removeClass( 'os-syncing' )
				.removeClass( 'stop-syncing' )
				.find( 'span' )
				.text( $syncBtn.data( 'label-sync' ) );
			return false;
		}

		const route = $trigger.data( 'os-action' );
		const params = $trigger.data( 'os-params' );
		const data = {
			action: latepoint_helper.route_action,
			route_name: route,
			params,
			layout: 'none',
			return_format: 'json',
		};

		$trigger.addClass( 'os-loading' );
		jQuery.ajax( {
			type: 'post',
			dataType: 'json',
			url: latepoint_timestamped_ajaxurl(),
			data,
			success: ( response ) => {
				if ( response.status === 'success' ) {
					$trigger
						.closest( '.os-booking-tiny-box' )
						.removeClass( 'not-synced' )
						.addClass( 'is-synced' );
					$trigger.removeClass( 'os-loading' );

					// Update progress bar
					const $wrapper = $trigger
						.closest( '.syncing-calendar-wrapper' )
						.find( '.os-sync-stats-and-progress-w' );
					const synced_total =
						parseInt(
							$wrapper.find( '.os-sync-value span' ).text()
						) + 1;
					$wrapper.find( '.os-sync-value span' ).text( synced_total );
					$wrapper
						.find( '.os-sync-progress' )
						.data( 'value', synced_total );
					const chart_total = $wrapper
						.find( '.os-sync-progress' )
						.data( 'total' );
					if ( chart_total > 0 ) {
						const percent = Math.min(
							Math.round( ( synced_total / chart_total ) * 100 ),
							100
						);
						$wrapper
							.find( '.os-sync-progress-bar' )
							.css( 'width', `${ percent }%` );
					}

					this.sync_next_booking_with_apple();
				}
			},
		} );
	}

	/**
	 * Initialize event handlers
	 */
	init() {
		jQuery( document ).ready( () => {
			// ============================================================
			// VIEW: _connection_form.php
			// TITLE: Apple Calendar Connection Form
			// ============================================================

			// Test connection button - handles Apple ID and app-specific password validation
			jQuery( document ).on(
				'click',
				'.apple-calendar-test-connection',
				( e ) => {
					e.preventDefault();
					const apple_id = jQuery( '#apple_calendar_apple_id' )
						.val()
						.trim();
					const app_password = jQuery(
						'#apple_calendar_app_password'
					)
						.val()
						.trim()
						.replace( /-/g, '' );
					const $result = jQuery(
						'.apple-calendar-connection-result'
					);

					if ( ! apple_id || ! app_password ) {
						$result.html(
							'<div class="os-form-message-w status-error">Please fill in both Apple ID and App-Specific Password</div>'
						);
						return;
					}

					const $btn = jQuery( e.currentTarget );
					const agent_id = $btn.data( 'agent-id' );
					$btn.addClass( 'os-loading' );
					$result.html( '' );

					const data = {
						action: 'latepoint_route_call',
						route_name: 'apple_calendar__test_connection',
						params: {
							agent_id,
							apple_id,
							app_password,
						},
						layout: 'none',
						return_format: 'json',
					};

					jQuery.ajax( {
						type: 'post',
						dataType: 'json',
						url: latepoint_timestamped_ajaxurl(),
						data,
						success: ( response ) => {
							$btn.removeClass( 'os-loading' );
							if ( response.status === 'success' ) {
								$result.html(
									`<div class="os-form-message-w status-success">${ response.message }<br>Reloading page...</div>`
								);
								setTimeout( () => location.reload(), 1500 );
							} else {
								$result.html(
									`<div class="os-form-message-w status-error">${ response.message }</div>`
								);
							}
						},
						error: () => {
							$btn.removeClass( 'os-loading' );
							$result.html(
								'<div class="os-form-message-w status-error">Connection failed. Please try again.</div>'
							);
						},
					} );
				}
			);

			// ============================================================
			// VIEW: render_calendar_selection.php
			// TITLE: Apple Calendar Selector Form
			// ============================================================

			// Calendar selector form submission - saves push/pull calendar selections
			jQuery( document ).on(
				'submit',
				'.apple-calendar-selection-form',
				( e ) => {
					e.preventDefault();

					const $form = jQuery( e.currentTarget );
					const agent_id = $form.data( 'agent-id' );
					const calendar_for_push = $form
						.find( 'select[name="calendar_for_push"]' )
						.val();
					const calendars_for_pull = [];
					const $result = jQuery(
						'.apple-calendar-selection-result'
					);

					$form
						.find( 'input[name="calendars_for_pull[]"]:checked' )
						.each( function () {
							calendars_for_pull.push( jQuery( this ).val() );
						} );

					const $submitBtn = $form.find( 'button[type="submit"]' );
					$submitBtn.addClass( 'os-loading' );
					$result.html( '' );

					const data = {
						action: 'latepoint_route_call',
						route_name: 'apple_calendar__save_calendar_selections',
						params: {
							agent_id,
							calendar_for_push,
							calendars_for_pull,
						},
						layout: 'none',
						return_format: 'json',
					};

					jQuery.ajax( {
						type: 'post',
						dataType: 'json',
						url: latepoint_timestamped_ajaxurl(),
						data,
						success: ( response ) => {
							$submitBtn.removeClass( 'os-loading' );
							if ( response.status === 'success' ) {
								$result.html(
									`<div class="os-form-message-w status-success">${ response.message }<br>Reloading page...</div>`
								);
								setTimeout( () => location.reload(), 1500 );
							} else {
								$result.html(
									`<div class="os-form-message-w status-error">${ response.message }</div>`
								);
							}
						},
						error: () => {
							$submitBtn.removeClass( 'os-loading' );
							$result.html(
								'<div class="os-form-message-w status-error">Failed to save selection. Please try again.</div>'
							);
						},
					} );
				}
			);

			// Cancel button - clears the calendar selector wrapper
			jQuery( document ).on(
				'click',
				'.apple-calendar-cancel-selection',
				( e ) => {
					e.preventDefault();
					jQuery( '.apple-calendar-selector-wrapper' ).html( '' );
				}
			);

			// ============================================================
			// VIEW: list_bookings_for_sync.php
			// TITLE: List Bookings for Sync
			// ============================================================

			// Calendar selector change handler - saves selected calendar for push
			jQuery( 'select.agent_apple_calendar_selector' ).on(
				'change',
				function () {
					const route = jQuery( this ).data( 'route' );
					const calendar_id = jQuery( this ).val();
					const agent_id = jQuery( this ).data( 'agent-id' );
					const data = {
						action: latepoint_helper.route_action,
						route_name: route,
						params: { calendar_id, agent_id },
						layout: 'none',
						return_format: 'json',
					};
					jQuery.ajax( {
						type: 'post',
						dataType: 'json',
						url: latepoint_timestamped_ajaxurl(),
						data,
						success: ( response ) => {
							if ( response.status === 'success' ) {
								latepoint_add_notification( response.message );
								location.reload();
							} else {
								// eslint-disable-next-line no-alert -- will be replaced with notification system in future updatesalert
								alert(
									'Error! Connecting calendar for push failed.'
								);
							}
						},
					} );
				}
			);

			// ============================================================
			// VIEW: load_events_for_sync.php
			// TITLE: Load Events for Sync
			// ============================================================

			// Toggle calendar sync - enables/disables calendar for pull
			// Listen to the hidden input change (triggered by LatePoint's toggler)
			jQuery( document ).on(
				'change',
				".calendar-available-for-sync input[type='hidden']",
				( e ) => {
					const $input = jQuery( e.currentTarget );
					const $container = $input.closest(
						'.calendar-available-for-sync'
					);

					// Prevent multiple rapid triggers
					if ( $container.hasClass( 'os-loading' ) ) {
						return false;
					}

					const agent_id = $container.data( 'agent-id' );
					const calendar_id = $container.data( 'calendar-id' );
					const route = $container.data( 'route' );
					const confirm_message = $container.data( 'confirm' );

					if (
						$container.hasClass( 'is-disconnected' ) ||
						// eslint-disable-next-line no-alert -- will be replaced with notification system in future updates
						confirm( confirm_message )
					) {
						$container.addClass( 'os-loading' );

						const data = {
							action: 'latepoint_route_call',
							route_name: route,
							params: {
								agent_id,
								calendar_id,
							},
							layout: 'none',
							return_format: 'json',
						};

						jQuery.ajax( {
							type: 'post',
							dataType: 'json',
							url: latepoint_timestamped_ajaxurl(),
							data,
							success: ( response ) => {
								if ( response.status === 'success' ) {
									location.reload();
								} else {
									// eslint-disable-next-line no-alert -- will be replaced with notification system in future updates
									// eslint-disable-next-line no-alert -- will be replaced with notification system in future updatesalert( response.message );
									$container.removeClass( 'os-loading' );
								}
							},
						} );
					} else {
						// User cancelled - revert the toggle
						e.preventDefault();
						const $toggler = $container.find( '.os-toggler' );
						$input.val( $input.val() === 'on' ? 'off' : 'on' );
						$toggler.toggleClass( 'on off' );
					}
				}
			);

			// Sync all events from Apple Calendar
			jQuery( document ).on(
				'click',
				'.sync-all-events-from-apple-trigger',
				( e ) => {
					e.preventDefault();
					const $btn = jQuery( e.currentTarget );

					if ( $btn.hasClass( 'is-syncing' ) ) {
						$btn.removeClass( 'is-syncing' );
						$btn.find( 'span' ).text( $btn.data( 'label-sync' ) );
						return;
					}

					$btn.addClass( 'is-syncing' );
					$btn.find( 'span' ).text(
						$btn.data( 'label-cancel-sync' )
					);

					jQuery( '.os-booking-tiny-box.not-synced' ).each(
						function () {
							if ( ! $btn.hasClass( 'is-syncing' ) ) return false;
							const $box = jQuery( this );
							$box.find(
								'.os-booking-sync-apple-trigger'
							).trigger( 'click' );
						}
					);

					setTimeout( () => {
						$btn.removeClass( 'is-syncing' );
						$btn.find( 'span' ).text( $btn.data( 'label-sync' ) );
					}, 1000 );
				}
			);

			// ============================================================
			// SHARED EVENT HANDLERS
			// ============================================================

			// Sync all bookings to Apple Calendar
			jQuery( '.sync-all-bookings-to-apple-trigger' ).on(
				'click',
				( e ) => {
					const $this = jQuery( e.currentTarget );
					if ( $this.hasClass( 'os-syncing' ) ) {
						$this.addClass( 'stop-syncing' );
						$this.find( 'span' ).text( $this.data( 'label-sync' ) );
					} else {
						$this
							.find( 'span' )
							.text( $this.data( 'label-cancel-sync' ) );
						$this.addClass( 'os-syncing' );
						latepointAppleCalendarAdminAddon.sync_next_booking_with_apple();
					}
					return false;
				}
			);

			// Remove all bookings from Apple Calendar
			jQuery( '.remove-all-bookings-from-apple-trigger' ).on(
				'click',
				( e ) => {
					const $this = jQuery( e.currentTarget );
					if ( $this.hasClass( 'os-removing' ) ) {
						$this.addClass( 'stop-removing' );
						$this
							.find( 'span' )
							.text( $this.data( 'label-remove' ) );
					} else {
						// eslint-disable-next-line no-alert -- will be replaced with notification system in future updates
						if ( ! confirm( $this.data( 'os-prompt' ) ) )
							return false;
						$this
							.find( 'span' )
							.text( $this.data( 'label-cancel-remove' ) );
						$this.addClass( 'os-removing' );
						latepointAppleCalendarAdminAddon.remove_first_synced_booking_with_apple();
					}
					return false;
				}
			);
		} );
	}

	/**
	 * ============================================================
	 * CALLBACK FUNCTIONS - Called via data-os-after-call attributes
	 * ============================================================
	 */

	/**
	 * VIEW: list_bookings_for_sync.php
	 * CALLBACK: Called after a booking is successfully synced to Apple Calendar
	 *
	 * @param {jQuery} $trigger - The jQuery element that triggered the callback
	 */
	booking_synced( $trigger ) {
		const $box = $trigger.closest( '.os-booking-tiny-box' );
		$box.removeClass( 'not-synced' ).addClass( 'is-synced' );

		// Update progress
		const $progressContainer = jQuery( '.os-sync-progress' );
		const current = parseInt( $progressContainer.data( 'value' ) );
		$progressContainer.data( 'value', current + 1 );
		const total = parseInt( $progressContainer.data( 'total' ) );
		const percent = Math.min(
			Math.round( ( ( current + 1 ) / total ) * 100 ),
			100
		);
		$progressContainer
			.find( '.os-sync-progress-bar' )
			.css( 'width', `${ percent }%` );

		// Update counter
		jQuery( '.os-sync-value span' ).text( current + 1 );
	}

	/**
	 * VIEW: list_bookings_for_sync.php
	 * CALLBACK: Called after a booking is unsynced from Apple Calendar
	 *
	 * @param {jQuery} $trigger - The jQuery element that triggered the callback
	 */
	booking_unsynced( $trigger ) {
		const $box = $trigger.closest( '.os-booking-tiny-box' );
		$box.removeClass( 'is-synced' ).addClass( 'not-synced' );

		// Update progress
		const $progressContainer = jQuery( '.os-sync-progress' );
		const current = parseInt( $progressContainer.data( 'value' ) );
		$progressContainer.data( 'value', current - 1 );
		const total = parseInt( $progressContainer.data( 'total' ) );
		const percent = Math.max(
			Math.round( ( ( current - 1 ) / total ) * 100 ),
			0
		);
		$progressContainer
			.find( '.os-sync-progress-bar' )
			.css( 'width', `${ percent }%` );

		// Update counter
		jQuery( '.os-sync-value span' ).text( current - 1 );
	}

	/**
	 * VIEW: load_events_for_sync.php
	 * CALLBACK: Called after an event is successfully synced from Apple Calendar
	 *
	 * @param {jQuery} $trigger - The jQuery element that triggered the callback
	 */
	event_synced( $trigger ) {
		const $box = $trigger.closest( '.os-booking-tiny-box' );
		$box.removeClass( 'not-synced' ).addClass( 'is-synced' );

		const $progressContainer = jQuery( '.os-sync-progress' );
		const current = parseInt( $progressContainer.data( 'value' ) );
		$progressContainer.data( 'value', current + 1 );
		const total = parseInt( $progressContainer.data( 'total' ) );
		const percent = Math.min(
			Math.round( ( ( current + 1 ) / total ) * 100 ),
			100
		);
		$progressContainer
			.find( '.os-sync-progress-bar' )
			.css( 'width', `${ percent }%` );

		jQuery( '.os-sync-value span' ).text( current + 1 );
	}

	/**
	 * VIEW: load_events_for_sync.php
	 * CALLBACK: Called after an event is unsynced from Apple Calendar
	 *
	 * @param {jQuery} $trigger - The jQuery element that triggered the callback
	 */
	event_unsynced( $trigger ) {
		const $box = $trigger.closest( '.os-booking-tiny-box' );
		$box.removeClass( 'is-synced' ).addClass( 'not-synced' );

		const $progressContainer = jQuery( '.os-sync-progress' );
		const current = parseInt( $progressContainer.data( 'value' ) );
		$progressContainer.data( 'value', current - 1 );
		const total = parseInt( $progressContainer.data( 'total' ) );
		const percent = Math.max(
			Math.round( ( ( current - 1 ) / total ) * 100 ),
			0
		);
		$progressContainer
			.find( '.os-sync-progress-bar' )
			.css( 'width', `${ percent }%` );

		jQuery( '.os-sync-value span' ).text( current - 1 );
	}
}

window.latepointAppleCalendarAdminAddon =
	new LatepointAppleCalendarAdminAddon();
