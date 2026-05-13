class LatepointOutlookCalendarAdminAddon {
	// Init
	constructor() {
		this.init();
	}

	booking_synced( $elem ) {
		$elem
			.closest( '.os-booking-tiny-box' )
			.removeClass( 'not-synced' )
			.addClass( 'is-synced' );
		this.sync_update_progress(
			$elem
				.closest( '.syncing-calendar-wrapper' )
				.find( '.os-sync-stats-and-progress-w' ),
			false
		);
	}

	ocal_event_deleted( $elem ) {
		$elem.closest( '.os-booking-tiny-box' ).remove();
	}

	booking_unsynced( $elem ) {
		$elem
			.closest( '.os-booking-tiny-box' )
			.addClass( 'not-synced' )
			.removeClass( 'is-synced' );
		this.sync_update_progress(
			$elem
				.closest( '.syncing-calendar-wrapper' )
				.find( '.os-sync-stats-and-progress-w' ),
			true
		);
	}

	sync_update_progress( $wrapper, is_removed ) {
		let synced_total = $wrapper.find( '.os-sync-value span' ).text();
		if ( is_removed ) {
			synced_total = parseInt( synced_total ) - 1;
		} else {
			synced_total = parseInt( synced_total ) + 1;
		}
		$wrapper.find( '.os-sync-value span' ).text( synced_total );
		$wrapper.find( '.os-sync-progress' ).data( 'value', synced_total );
		const chart_total = $wrapper
			.find( '.os-sync-progress' )
			.data( 'total' );
		const chart_value = $wrapper
			.find( '.os-sync-progress' )
			.data( 'value' );
		if ( chart_total > 0 ) {
			const percent = Math.min(
				Math.round( ( chart_value / chart_total ) * 100 ),
				100
			);
			$wrapper
				.find( '.os-sync-progress-bar' )
				.css( 'width', percent + '%' );
		}
	}

	remove_first_synced_booking_with_outlook() {
		const $trigger = jQuery(
			'.os-booking-tiny-box.is-synced:first .os-booking-sync-outlook-trigger'
		);
		if (
			! $trigger.length ||
			jQuery( '.remove-all-bookings-from-outlook-trigger' ).hasClass(
				'stop-removing'
			)
		) {
			jQuery( '.remove-all-bookings-from-outlook-trigger' )
				.removeClass( 'os-removing' )
				.removeClass( 'stop-removing' )
				.find( 'span' )
				.text(
					jQuery( '.remove-all-bookings-from-outlook-trigger' ).data(
						'label-remove'
					)
				);
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
			success( response ) {
				if ( response.status === 'success' ) {
					$trigger
						.closest( '.os-booking-tiny-box' )
						.removeClass( 'is-synced' )
						.addClass( 'not-synced' );
					$trigger.removeClass( 'os-loading' );
					latepointOutlookCalendarAdminAddon.sync_update_progress(
						$trigger
							.closest( '.syncing-calendar-wrapper' )
							.find( '.os-sync-stats-and-progress-w' ),
						true
					);
					latepointOutlookCalendarAdminAddon.remove_first_synced_booking_with_outlook();
				}
			},
		} );
	}

	sync_next_booking_with_outlook() {
		const $trigger = jQuery(
			'.os-booking-tiny-box.not-synced:first .os-booking-sync-outlook-trigger'
		);
		if (
			! $trigger.length ||
			jQuery( '.sync-all-bookings-to-outlook-trigger' ).hasClass(
				'stop-syncing'
			)
		) {
			jQuery( '.sync-all-bookings-to-outlook-trigger' )
				.removeClass( 'os-syncing' )
				.removeClass( 'stop-syncing' )
				.find( 'span' )
				.text(
					jQuery( '.sync-all-bookings-to-outlook-trigger' ).data(
						'label-sync'
					)
				);
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
			success( response ) {
				if ( response.status === 'success' ) {
					$trigger
						.closest( '.os-booking-tiny-box' )
						.removeClass( 'not-synced' )
						.addClass( 'is-synced' );
					$trigger.removeClass( 'os-loading' );
					latepointOutlookCalendarAdminAddon.sync_update_progress(
						$trigger
							.closest( '.syncing-calendar-wrapper' )
							.find( '.os-sync-stats-and-progress-w' ),
						false
					);
					latepointOutlookCalendarAdminAddon.sync_next_booking_with_outlook();
				}
			},
		} );
	}

	/**
	 *  On load, called to load the auth2 library and API client library.
	 */
	init() {
		jQuery( document ).ready( () => {
			jQuery( 'select.agent_outlook_calendar_selector' ).on(
				'change',
				function () {
					const $this = jQuery( this );
					const route = $this.data( 'route' );
					const calendar_id = $this.val();
					const agent_id = $this.data( 'agent-id' );
					const data = {
						action: latepoint_helper.route_action,
						route_name: route,
						params: {
							calendar_id,
							agent_id,
						},
						layout: 'none',
						return_format: 'json',
					};
					jQuery.ajax( {
						type: 'post',
						dataType: 'json',
						url: latepoint_timestamped_ajaxurl(),
						data,
						success( resp ) {
							if ( resp.status === 'success' ) {
								latepoint_add_notification( resp.message );
								location.reload();
							} else {
								// eslint-disable-next-line no-alert
								alert(
									'Error! Connecting calendar for push failed.'
								);
							}
						},
					} );
				}
			);

			jQuery( '.sync-all-bookings-to-outlook-trigger' ).on(
				'click',
				function () {
					if ( jQuery( this ).hasClass( 'os-syncing' ) ) {
						jQuery( this ).addClass( 'stop-syncing' );
						jQuery( this )
							.find( 'span' )
							.text( jQuery( this ).data( 'label-sync' ) );
					} else {
						jQuery( this )
							.find( 'span' )
							.text( jQuery( this ).data( 'label-cancel-sync' ) );
						jQuery( this ).addClass( 'os-syncing' );
						latepointOutlookCalendarAdminAddon.sync_next_booking_with_outlook();
					}
					return false;
				}
			);

			jQuery( '.remove-all-bookings-from-outlook-trigger' ).on(
				'click',
				function () {
					if ( jQuery( this ).hasClass( 'os-removing' ) ) {
						jQuery( this ).addClass( 'stop-removing' );
						jQuery( this )
							.find( 'span' )
							.text( jQuery( this ).data( 'label-remove' ) );
					} else {
						// eslint-disable-next-line no-alert
						if ( ! confirm( jQuery( this ).data( 'os-prompt' ) ) )
							return false;
						jQuery( this )
							.find( 'span' )
							.text(
								jQuery( this ).data( 'label-cancel-remove' )
							);
						jQuery( this ).addClass( 'os-removing' );
						latepointOutlookCalendarAdminAddon.remove_first_synced_booking_with_outlook();
					}
					return false;
				}
			);

			jQuery( '.calendar-available-for-sync input[type="hidden"]' ).on(
				'change',
				function () {
					const $elem_wrapper = jQuery( this ).closest(
						'.calendar-available-for-sync'
					);
					const route = $elem_wrapper.data( 'route' );
					const calendar_id = $elem_wrapper.data( 'calendar-id' );
					const agent_id = $elem_wrapper.data( 'agent-id' );
					const data = {
						action: latepoint_helper.route_action,
						route_name: route,
						params: {
							calendar_id,
							agent_id,
						},
						return_format: 'json',
					};
					$elem_wrapper.addClass( 'os-loading' );
					jQuery.ajax( {
						type: 'post',
						dataType: 'json',
						url: latepoint_timestamped_ajaxurl(),
						data,
						success( response ) {
							$elem_wrapper.removeClass( 'os-loading' );
							if ( response.status === 'success' ) {
								latepoint_add_notification( response.message );
								location.reload();
							} else {
								// eslint-disable-next-line no-alert
								alert( response.message, 'error' );
							}
						},
					} );
				}
			);
		} );
	}
}

window.latepointOutlookCalendarAdminAddon =
	new LatepointOutlookCalendarAdminAddon();
