<?php
/* @var $agent OsAgentModel */
/* @var $services OsServiceModel[] */
/* @var $locations OsLocationModel[] */
/* @var $wp_users_for_select array */
?>
<form action=""
	<?php if ( OsAuthHelper::get_current_user()->backend_user_type == LATEPOINT_USER_TYPE_AGENT ) {
		echo 'data-os-success-action="reload"';
	} else {
		echo 'data-os-success-action="redirect" data-os-redirect-to="' . OsRouterHelper::build_link( [ 'agents', 'index' ] ) . '"';
	}
	?>
      data-os-record-id-holder="agent[id]"
      data-os-action="<?php echo $agent->is_new_record() ? OsRouterHelper::build_route_name( 'agents', 'create' ) : OsRouterHelper::build_route_name( 'agents', 'update' ); ?>">
    <div class="latepoint-page-with-side-nav">
        <div class="os-form-w">
            <div class="white-box section-anchor" id="stickySectionGeneral">
                <div class="white-box-header">
                    <div class="os-form-sub-header">
                        <h3><?php _e( 'General Information', 'latepoint-pro-features' ); ?></h3>
						<?php if ( ! $agent->is_new_record() ) { ?>
                            <div class="os-form-sub-header-actions os-highlight"><?php echo sprintf( esc_html__( 'Agent ID: %d', 'latepoint-pro-features' ), esc_html( $agent->id ) ); ?></div>
						<?php } ?>
                    </div>
                </div>
                <div class="white-box-content">
					<?php echo OsFormHelper::media_uploader_field( 'agent[avatar_image_id]', 0, __( 'Set Avatar', 'latepoint-pro-features' ), __( 'Remove Avatar', 'latepoint-pro-features' ), $agent->avatar_image_id, [], [], true ); ?>
                    <div class="os-row">
                        <div class="os-col-lg-4"><?php echo OsFormHelper::text_field( 'agent[first_name]', __( 'First Name', 'latepoint-pro-features' ), $agent->first_name ); ?></div>
                        <div class="os-col-lg-4"><?php echo OsFormHelper::text_field( 'agent[last_name]', __( 'Last Name', 'latepoint-pro-features' ), $agent->last_name ); ?></div>
                        <div class="os-col-lg-4"><?php echo OsFormHelper::text_field( 'agent[display_name]', __( 'Display Name', 'latepoint-pro-features' ), $agent->display_name ); ?></div>
                    </div>
                    <div class="os-row">
                        <div class="os-col-lg-4"><?php echo OsFormHelper::text_field( 'agent[email]', __( 'Email Address', 'latepoint-pro-features' ), $agent->email ); ?></div>
                        <div class="os-col-lg-4"><?php echo OsFormHelper::phone_number_field( 'agent[phone]', __( 'Phone Number', 'latepoint-pro-features' ), $agent->phone ); ?></div>
                    </div>
					<?php if ( OsRolesHelper::can_user( 'settings__edit' ) ) { ?>
                        <div class="os-row">
                            <div class="os-col-4"><?php echo OsFormHelper::select_field( 'agent[wp_user_id]', __( 'Connect to WP User', 'latepoint-pro-features' ), $wp_users_for_select, $agent->wp_user_id, [ 'placeholder' => __( 'Select User', 'latepoint-pro-features' ) ] ); ?></div>
                            <div class="os-col-4"><?php echo OsFormHelper::select_field( 'agent[status]', __( 'Status', 'latepoint-pro-features' ), array( LATEPOINT_AGENT_STATUS_ACTIVE   => __( 'Active', 'latepoint-pro-features' ),
							                                                                                                                               LATEPOINT_AGENT_STATUS_DISABLED => __( 'Disabled', 'latepoint-pro-features' )
								), $agent->status ); ?></div>
                        </div>
					<?php } ?>
                </div>
            </div>
            <div class="white-box section-anchor" id="stickySectionContacts">
                <div class="white-box-header">
                    <div class="os-form-sub-header">
                        <h3><?php _e( 'Additional Contacts', 'latepoint-pro-features' ); ?></h3>
                    </div>
                </div>
                <div class="white-box-content">
                    <div class="latepoint-message latepoint-message-subtle"><?php _e( 'If you need to notify multiple persons about the appointment, you can list additional email addresses and phone numbers to send notification emails and sms to. You can list multiple numbers and emails separated by commas.', 'latepoint-pro-features' ); ?></div>
                    <div class="os-row">
                        <div class="os-col-lg-6"><?php echo OsFormHelper::text_field( 'agent[extra_emails]', __( 'Additional Email Addresses', 'latepoint-pro-features' ), $agent->extra_emails ); ?></div>
                        <div class="os-col-lg-6"><?php echo OsFormHelper::text_field( 'agent[extra_phones]', __( 'Additional Phone Numbers', 'latepoint-pro-features' ), $agent->extra_phones ); ?></div>
                    </div>
                </div>
            </div>
            <div class="white-box section-anchor" id="stickySectionExtra">
                <div class="white-box-header">
                    <div class="os-form-sub-header">
                        <h3><?php _e( 'Extra Information', 'latepoint-pro-features' ); ?></h3>
                    </div>
                </div>
                <div class="white-box-content">

					<?php echo OsFormHelper::media_uploader_field( 'agent[bio_image_id]', 0, __( 'Set Bio Image', 'latepoint-pro-features' ), __( 'Remove Bio Image', 'latepoint-pro-features' ), $agent->bio_image_id ); ?>
					<?php echo OsFormHelper::text_field( 'agent[title]', __( 'Agent Title', 'latepoint-pro-features' ), $agent->title ); ?>
					<?php echo OsFormHelper::textarea_field( 'agent[bio]', __( 'Bio Text', 'latepoint-pro-features' ), $agent->bio, array( 'rows' => 5 ) ); ?>
                    <h3><?php _e( 'Agent Highlights', 'latepoint-pro-features' ) ?></h3>
                    <div class="latepoint-message latepoint-message-subtle"><?php _e( 'These value-label pairs will appear on agent information popup. You can enter things like years of experience, or number of clients served, to highlight agent accomplishments.', 'latepoint-pro-features' ); ?></div>
                    <div class="os-agent-highlights">
						<?php for ( $i = 0; $i < 3; $i ++ ) {
							$feature_value = isset( $agent->features_arr[ $i ] ) ? $agent->features_arr[ $i ]['value'] : '';
							$feature_label = isset( $agent->features_arr[ $i ] ) ? $agent->features_arr[ $i ]['label'] : ''; ?>
                            <div class="os-agent-highlight">
                                <h4><?php echo __( 'Highlight #', 'latepoint-pro-features' ) . ( $i + 1 ); ?></h4>
                                <div class="os-agent-highlight-fields">
									<?php echo OsFormHelper::text_field( 'agent[features][' . $i . '][value]', __( 'Value', 'latepoint-pro-features' ), $feature_value ); ?>
									<?php echo OsFormHelper::text_field( 'agent[features][' . $i . '][label]', __( 'Label', 'latepoint-pro-features' ), $feature_label ); ?>
                                </div>
                            </div>
						<?php } ?>
                    </div>
                </div>
            </div>
			<?php if ( OsRolesHelper::can_user( 'connection__edit' ) ) { ?>
                <div class="white-box section-anchor" id="stickySectionServices">
                    <div class="white-box-header">
                        <div class="os-form-sub-header">
                            <h3><?php _e( 'Offered Services', 'latepoint-pro-features' ); ?></h3>
                            <div class="os-form-sub-header-actions">
								<?php echo OsFormHelper::checkbox_field( 'select_all_services', __( 'Select All', 'latepoint-pro-features' ), 'on', $agent->is_new_record(), [ 'class' => 'os-select-all-toggler' ] ); ?>
                            </div>
                        </div>
                    </div>
                    <div class="white-box-content">
                        <div class="os-complex-connections-selector">
							<?php if ( $services ) {
								foreach ( $services as $service ) {
									$is_connected       = $agent->is_new_record() ? true : $agent->has_service( $service->id );
									$is_connected_value = $is_connected ? 'yes' : 'no';
									if ( $locations ) {
										if ( count( $locations ) > 1 ) {
											// multiple locations
											$locations_count = $agent->count_number_of_connected_locations( $service->id );
											if ( $locations_count == count( $locations ) ) {
												$locations_count_string = __( 'All', 'latepoint-pro-features' );
											} else {
												$locations_count_string = $agent->is_new_record() ? __( 'All', 'latepoint-pro-features' ) : $locations_count . '/' . count( $locations );
											} ?>
                                        <div class="connection <?php echo $is_connected ? 'active' : ''; ?>">
                                            <div class="connection-i selector-trigger">
                                            <h3 class="connection-name"><?php echo $service->name; ?></h3>
                                            <div class="selected-connections" data-all-text="<?php echo __( 'All', 'latepoint-pro-features' ); ?>">
                                                <strong><?php echo $locations_count_string; ?></strong>
                                                <span><?php echo __( 'Locations Selected', 'latepoint-pro-features' ); ?></span>
                                            </div>
                                            <a href="#" class="customize-connection-btn"><i
                                                        class="latepoint-icon latepoint-icon-ui-46"></i><span><?php echo __( 'Customize', 'latepoint-pro-features' ); ?></span></a>
                                            </div><?php
											if ( $locations ) { ?>
                                                <div class="connection-children-list-w">
                                                <h4><?php echo sprintf( __( 'Select locations where this agent will offer %s:', 'latepoint-pro-features' ), $service->name ); ?></h4>
                                                <ul class="connection-children-list"><?php
													foreach ( $locations as $location ) {
														$is_connected       = $agent->is_new_record() ? true : $location->has_agent_and_service( $agent->id, $service->id );
														$is_connected_value = $is_connected ? 'yes' : 'no'; ?>
                                                        <li class="<?php echo $is_connected ? 'active' : ''; ?>">
															<?php echo OsFormHelper::hidden_field( 'agent[services][service_' . $service->id . '][location_' . $location->id . '][connected]', $is_connected_value, array( 'class' => 'connection-child-is-connected' ) ); ?>
															<?php echo $location->name; ?>
                                                            <?php if($location->status == LATEPOINT_LOCATION_STATUS_DISABLED){
                                                                echo '[' . __( 'Disabled', 'latepoint-pro-features' ) . ']';
                                                            }
                                                            ?>
                                                        </li>
													<?php } ?>
                                                </ul>
                                                </div><?php
											} ?>
                                            </div><?php
										} else {
											// one location
											$location           = $locations[0];
											$is_connected       = $agent->is_new_record() ? true : $location->has_agent_and_service( $agent->id, $service->id );
											$is_connected_value = $is_connected ? 'yes' : 'no';
											?>
                                            <div class="connection <?php echo $is_connected ? 'active' : ''; ?>">
                                                <div class="connection-i selector-trigger">
                                                    <div class="connection-avatar"><img src="<?php echo $service->get_selection_image_url(); ?>"/></div>
                                                    <h3 class="connection-name"><?php echo $service->name; ?></h3>
													<?php echo OsFormHelper::hidden_field( 'agent[services][service_' . $service->id . '][location_' . $location->id . '][connected]', $is_connected_value, array( 'class' => 'connection-child-is-connected' ) ); ?>
                                                </div>
                                            </div>
											<?php
										}
									}
								}
							} else { ?>
                                <div class="no-results-w">
                                    <div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
                                    <h2><?php _e( 'No Existing Services Found', 'latepoint-pro-features' ); ?></h2>
                                    <a href="<?php echo OsRouterHelper::build_link( [ 'services', 'new_form' ] ) ?>" class="latepoint-btn"><i
                                                class="latepoint-icon latepoint-icon-plus"></i><span><?php _e( 'Add First Service', 'latepoint-pro-features' ); ?></span></a>
                                </div> <?php
							}
							?>
                        </div>
                    </div>
                </div>
			<?php } ?>
			<?php if ( OsRolesHelper::can_user( 'resource_schedule__edit' ) ) { ?>
				<?php
				$has_multiple_locations = ! $agent->is_new_record() && count( $agent_location_ids ) > 1;
				$connected_locations    = [];
				if ( $has_multiple_locations ) {
					foreach ( $locations as $loc ) {
						if ( in_array( $loc->id, $agent_location_ids ) ) {
							$connected_locations[] = $loc;
						}
					}
				}
				?>
                <div class="white-box section-anchor" id="stickySectionSchedule">
                    <div class="white-box-header">
                        <div class="os-form-sub-header">
                            <h3><?php _e( 'Agent Schedule', 'latepoint-pro-features' ); ?></h3>
							<?php if ( $has_multiple_locations ) :
								$schedule_location_selector_id = 'schedule-location-selector';
								include '_schedule_location_selector.php';
							endif; ?>
                        </div>
                    </div>
                    <div class="white-box-content">
						<div class="agent-schedule-tabs" data-tab-prefix="schedule">
							<?php
							$general_schedule_key = 'schedules[general]';
							$filter               = new \LatePoint\Misc\Filter();
							if ( ! $agent->is_new_record() ) {
								$filter->agent_id = $agent->id;
							}
							?>
							<div class="schedule-tab-panel is-active" id="schedule-tab-location-general">
								<div class="os-form-sub-header-actions">
									<?php echo OsFormHelper::checkbox_field( $general_schedule_key . '[is_custom_schedule]', __( 'Set Custom Schedule', 'latepoint-pro-features' ), 'on', $is_custom_schedule, array( 'data-toggle-element' => '.custom-schedule-wrapper-general' ) ); ?>
								</div>
								<div class="custom-schedule-wrapper custom-schedule-wrapper-general" style="<?php if ( ! $is_custom_schedule ) { echo 'display: none;'; } ?>">
									<?php OsWorkPeriodsHelper::generate_work_periods( $custom_work_periods, $filter, $agent->is_new_record(), $general_schedule_key . '[work_periods]' ); ?>
								</div>
								<div class="custom-schedule-wrapper custom-schedule-wrapper-general" style="<?php if ( $is_custom_schedule ) { echo 'display: none;'; } ?>">
									<div class="latepoint-message latepoint-message-subtle"><?php _e( 'This agent is using general schedule which is set in main settings', 'latepoint-pro-features' ); ?></div>
								</div>
							</div>

							<?php if ( $has_multiple_locations ) : ?>
								<?php foreach ( $connected_locations as $loc ) : ?>
									<?php
									$loc_periods      = isset( $location_work_periods[ $loc->id ] ) ? $location_work_periods[ $loc->id ] : [];
									$loc_is_custom_schedule    = ! empty( $loc_periods );
									$loc_schedule_key = 'schedules[location_' . $loc->id . ']';
									$loc_filter       = new \LatePoint\Misc\Filter();
									$loc_filter->agent_id    = $agent->id;
									$loc_filter->location_id = $loc->id;
									?>
									<div class="schedule-tab-panel" id="schedule-tab-location-<?php echo esc_attr( $loc->id ); ?>">
										<div class="os-form-sub-header-actions">
											<?php echo OsFormHelper::checkbox_field( $loc_schedule_key . '[is_custom_schedule]', sprintf( __( 'Set Custom Schedule for %s', 'latepoint-pro-features' ), esc_html( $loc->name ) ), 'on', $loc_is_custom_schedule, array( 'data-toggle-element' => '.custom-schedule-wrapper-location-' . esc_attr( $loc->id ) ) ); ?>
										</div>
										<div class="custom-schedule-wrapper custom-schedule-wrapper-location-<?php echo esc_attr( $loc->id ); ?>" style="<?php if ( ! $loc_is_custom_schedule ) { echo 'display: none;'; } ?>">
											<?php OsWorkPeriodsHelper::generate_work_periods( $loc_periods, $loc_filter, false, $loc_schedule_key . '[work_periods]' ); ?>
										</div>
										<div class="custom-schedule-wrapper custom-schedule-wrapper-location-<?php echo esc_attr( $loc->id ); ?>" style="<?php if ( $loc_is_custom_schedule ) { echo 'display: none;'; } ?>">
											<div class="latepoint-message latepoint-message-subtle"><?php _e( 'This location is using the agent\'s general schedule', 'latepoint-pro-features' ); ?></div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
                    </div>
                </div>

				<?php if ( ! $agent->is_new_record() ) { ?>
                    <div class="white-box section-anchor" id="stickySectionCustomSchedule">
                        <div class="white-box-header">
                            <div class="os-form-sub-header">
								<h3><?php _e( 'Days With Custom Schedules', 'latepoint-pro-features' ); ?></h3>
								<?php if ( $has_multiple_locations ) :
									$schedule_location_selector_id = 'custom-days-location-selector';
									include '_schedule_location_selector.php';
								endif; ?>
							</div>
                        </div>
                        <div class="white-box-content">
                            <div class="latepoint-message latepoint-message-subtle"><?php _e( 'Agent shares custom daily schedules that you set in general settings for your company, however you can add additional days with custom hours which will be specific to this agent only.', 'latepoint-pro-features' ); ?></div>
							<div class="agent-schedule-tabs" data-tab-prefix="custom-days">
								<div class="schedule-tab-panel is-active" id="custom-days-tab-location-general">
									<?php OsWorkPeriodsHelper::generate_days_with_custom_schedule( [ 'agent_id' => $agent->id, 'location_id' => 0 ] ); ?>
								</div>
								<?php if ( $has_multiple_locations ) : ?>
									<?php foreach ( $connected_locations as $loc ) : ?>
										<div class="schedule-tab-panel" id="custom-days-tab-location-<?php echo esc_attr( $loc->id ); ?>">
											<?php OsWorkPeriodsHelper::generate_days_with_custom_schedule( [ 'agent_id' => $agent->id, 'location_id' => $loc->id ] ); ?>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
                        </div>
                    </div>
                    <div class="white-box section-anchor" id="stickySectionHolidays">
                        <div class="white-box-header">
                            <div class="os-form-sub-header">
								<h3><?php _e( 'Holidays & Days Off', 'latepoint-pro-features' ); ?></h3>
								<?php if ( $has_multiple_locations ) :
									$schedule_location_selector_id = 'holidays-location-selector';
									include '_schedule_location_selector.php';
								endif; ?>
							</div>
                        </div>
                        <div class="white-box-content">
                            <div class="latepoint-message latepoint-message-subtle"><?php _e( 'Agent uses the same holidays you set in general settings for your company, however you can add additional holidays for this agent here.', 'latepoint-pro-features' ); ?></div>
							<div class="agent-schedule-tabs" data-tab-prefix="holidays">
								<div class="schedule-tab-panel is-active" id="holidays-tab-location-general">
									<?php OsWorkPeriodsHelper::generate_off_days( [ 'agent_id' => $agent->id, 'location_id' => 0 ] ); ?>
								</div>
								<?php if ( $has_multiple_locations ) : ?>
									<?php foreach ( $connected_locations as $loc ) : ?>
										<div class="schedule-tab-panel" id="holidays-tab-location-<?php echo esc_attr( $loc->id ); ?>">
											<?php OsWorkPeriodsHelper::generate_off_days( [ 'agent_id' => $agent->id, 'location_id' => $loc->id ] ); ?>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
                        </div>
                    </div>
				<?php } ?>
			<?php } ?>
			<?php do_action( 'latepoint_agent_form', $agent ); ?>
            <div class="os-form-buttons os-flex hidden-with-side-nav">
				<?php
				$extra_actions_html = '';
				if ( $agent->is_new_record() ) {
					echo OsFormHelper::hidden_field( 'agent[id]', '' );
					echo OsFormHelper::button( 'submit', __( 'Add Agent', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] );
				} else {
					echo OsFormHelper::hidden_field( 'agent[id]', $agent->id );

					if ( OsRolesHelper::can_user( 'agent__delete' ) || OsRolesHelper::can_user( 'agent__edit' ) ) {
                        $extra_actions_html .= '<div class="os-trigger-dots"><div class="os-trigger-dots-context">';
						if ( OsRolesHelper::can_user( 'agent__delete' ) ) {
							$extra_actions_html .= '<div class="os-context-item os-danger"
                            data-os-prompt="' . __( 'Are you sure you want to remove this agent?', 'latepoint-pro-features' ) . '"
                            data-os-redirect-to="' . OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'agents', 'index' ) ) . '"
                            data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $agent->id ], 'destroy_agent_' . $agent->id ) . '"
                            data-os-success-action="redirect"
                            data-os-action="' . OsRouterHelper::build_route_name( 'agents', 'destroy' ) . '"><i class="latepoint-icon latepoint-icon-trash-2"></i><span>' . __( 'Delete', 'latepoint-pro-features' ) . '</span></div>';
						}
						if ( OsRolesHelper::can_user( 'agent__edit' ) ) {

							$extra_actions_html .= '<div class="os-context-item"
                            data-os-prompt="' . __( 'Are you sure you want to duplicate this agent?', 'latepoint-pro-features' ) . '"
                            data-os-success-action="redirect"
                            data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $agent->id ], 'duplicate_agent_' . $agent->id ) . '"
                            data-os-action="' . OsRouterHelper::build_route_name( 'agents', 'duplicate' ) . '"><i class="latepoint-icon latepoint-icon-copy"></i><span>' . __( 'Duplicate', 'latepoint-pro-features' ) . '</span></div>';

						}

						$extra_actions_html .= '</div><i class="latepoint-icon latepoint-icon-more-horizontal"></i></div>';
						echo $extra_actions_html;
						if ( OsRolesHelper::can_user( 'agent__edit' ) ) {
                        echo OsFormHelper::button( 'submit', __( 'Save Changes', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] );
                        }
					}


				}
				?>
            </div>
			<?php wp_nonce_field( $agent->is_new_record() ? 'new_agent' : 'edit_agent_' . $agent->id ); ?>
        </div>
        <div class="latepoint-page-side-nav">
			<?php if ( OsRolesHelper::can_user( 'agent__edit' ) ) { ?>
                <div class="side-nav-actions">
					<?php echo $extra_actions_html; ?>
                    <button type="submit" class="latepoint-btn latepoint-btn-block"><i class="latepoint-icon latepoint-icon-check"></i><span><?php _e( 'Save Changes', 'latepoint' ); ?></span></button>
                </div>
			<?php } ?>
            <div class="side-nav-body">
                <div><a href="#stickySectionGeneral" class="is-active"><?php esc_html_e( 'General', 'latepoint' ); ?></a></div>
                <div><a href="#stickySectionContacts"><?php esc_html_e( 'Additional Contacts', 'latepoint' ); ?></a></div>
                <div><a href="#stickySectionExtra"><?php esc_html_e( 'Extra Info', 'latepoint' ); ?></a></div>
                <div><a href="#stickySectionServices"><?php esc_html_e( 'Services', 'latepoint' ); ?></a></div>
                <div><a href="#stickySectionSchedule"><?php esc_html_e( 'Schedule', 'latepoint' ); ?></a></div>

				<?php if ( ! $agent->is_new_record() ) { ?>
                    <div><a href="#stickySectionCustomSchedule"><?php esc_html_e( 'Custom Days', 'latepoint' ); ?></a></div>
                    <div><a href="#stickySectionHolidays"><?php esc_html_e( 'Holidays & Days off', 'latepoint' ); ?></a></div>
				<?php } ?>
				<?php

				/**
				 * Sticky menu items links for the agent edit form
				 *
				 * @param {array} $sticky_menu_items items that go into sticky menu on the right of settings, in format ['href' => '', 'label' => '']
				 * @returns {array} The filtered array of sticky menu items
				 *
				 * @since 5.2.0
				 * @hook latepoint_agent_edit_form_sticky_section_items
				 *
				 */
				$before_other_items = apply_filters( 'latepoint_agent_edit_form_sticky_section_items', [] );
				foreach ( $before_other_items as $item ) {
					echo '<div><a href="#' . esc_attr( $item['href'] ) . '">' . esc_html( $item['label'] ) . '</a></div>';
				}
				?>
            </div>
        </div>
    </div>
</form>
