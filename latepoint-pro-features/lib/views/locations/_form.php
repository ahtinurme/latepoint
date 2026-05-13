<form action="" data-os-success-action="redirect" data-os-redirect-to="<?php echo OsRouterHelper::build_link( [ 'locations', 'index' ] ); ?>"
      data-os-action="<?php echo $location->is_new_record() ? OsRouterHelper::build_route_name( 'locations', 'create' ) : OsRouterHelper::build_route_name( 'locations', 'update' ); ?>">
    <div class="latepoint-page-with-side-nav">
        <div class="os-form-w">
            <div class="white-box section-anchor" id="stickySectionGeneral">
                <div class="white-box-header">
                    <div class="os-form-sub-header">
                        <h3><?php _e( 'Basic Information', 'latepoint-pro-features' ); ?></h3>
						<?php if ( ! $location->is_new_record() ) { ?>
                            <div class="os-form-sub-header-actions os-highlight"><?php echo sprintf( esc_html__( 'Location ID: %d', 'latepoint-pro-features' ), esc_html( $location->id ) ); ?></div>
						<?php } ?>
                    </div>
                </div>
                <div class="white-box-content">
                    <div class="os-row">
                        <div class="os-col-lg-4">
							<?php echo OsFormHelper::text_field( 'location[name]', __( 'Location Name', 'latepoint-pro-features' ), $location->name, ['theme' => 'simple'] ); ?>
							<?php echo OsFormHelper::select_field( 'location[status]', __( 'Status', 'latepoint-pro-features' ), array( LATEPOINT_SERVICE_STATUS_ACTIVE   => __( 'Active', 'latepoint-pro-features' ),
							                                                                                                            LATEPOINT_SERVICE_STATUS_DISABLED => __( 'Disabled', 'latepoint-pro-features' )
							), $location->status ); ?>
							<?php echo OsFormHelper::location_selector_adder_field( 'location[category_id]', __( 'Category', 'latepoint-pro-features' ), __( 'Add Category', 'latepoint-pro-features' ), $location_categories_for_select, $location->category_id ); ?>
							<?php echo OsFormHelper::media_uploader_field( 'location[selection_image_id]', 0, __( 'Location Photo', 'latepoint-pro-features' ), __( 'Remove Image', 'latepoint-pro-features' ), $location->selection_image_id ); ?>
                        </div>
                        <div class="os-col-lg-8">
							<?php echo OsFormHelper::text_field( 'location[full_address]', __( 'Location Address', 'latepoint-pro-features' ), $location->full_address, ['theme' => 'simple'] ); ?>
							<?php if ( $location->full_address ) { ?>
                                <iframe class="location-edit-iframe" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"
                                        src="https://maps.google.it/maps?q=<?php echo urlencode( $location->full_address ); ?>&output=embed"></iframe>
							<?php }else{
                                echo '<div class="location-edit-placeholder"><i class="latepoint-icon latepoint-icon-map-marker"></i></div>';
                            } ?>
                        </div>
                    </div>
                </div>
            </div>


			<?php if ( OsRolesHelper::can_user( 'connection__edit' ) ) { ?>
                <div class="white-box section-anchor" id="stickySectionAgents">
                    <div class="white-box-header">
                        <div class="os-form-sub-header">
                            <h3><?php _e( 'Select Agents for This Location', 'latepoint-pro-features' ); ?></h3>
                            <div class="os-form-sub-header-actions">
								<?php echo OsFormHelper::checkbox_field( 'select_all_agents', __( 'Select All', 'latepoint-pro-features' ), 'on', $location->is_new_record(), [ 'class' => 'os-select-all-toggler' ] ); ?>
                            </div>
                        </div>
                    </div>
                    <div class="white-box-content">
                        <div class="os-complex-connections-selector">
							<?php if ( $agents ) {
								foreach ( $agents as $agent ) {
									$is_connected       = $location->is_new_record() ? true : $location->has_agent( $agent->id );
									$is_connected_value = $is_connected ? 'yes' : 'no';
									if ( $services ) {
										if ( count( $services ) > 1 ) {
											// multiple services
											$services_count = $location->count_number_of_connected_services( $agent->id );
											if ( $services_count == count( $services ) ) {
												$services_count_string = __( 'All', 'latepoint-pro-features' );
											} else {
												$services_count_string = $location->is_new_record() ? __( 'All', 'latepoint-pro-features' ) : $services_count . '/' . count( $services );
											} ?>
                                        <div class="connection <?php echo $is_connected ? 'active' : ''; ?>">
                                            <div class="connection-i selector-trigger">
                                            <div class="connection-avatar"><img src="<?php echo $agent->get_avatar_url(); ?>"/></div>
                                            <h3 class="connection-name"><?php echo esc_html( $agent->full_name ); ?></h3>
                                            <div class="selected-connections" data-all-text="<?php echo __( 'All', 'latepoint-pro-features' ); ?>">
                                                <strong><?php echo $services_count_string; ?></strong>
                                                <span><?php echo __( 'Services Selected', 'latepoint-pro-features' ); ?></span>
                                            </div>
                                            <a href="#" class="customize-connection-btn"><i
                                                        class="latepoint-icon latepoint-icon-ui-46"></i><span><?php echo __( 'Customize', 'latepoint-pro-features' ); ?></span></a>
                                            </div><?php
											if ( $services ) { ?>
                                                <div class="connection-children-list-w">
                                                <h4><?php echo sprintf( __( 'Select services that %s will be offering at this location:', 'latepoint-pro-features' ), $agent->first_name ); ?></h4>
                                                <ul class="connection-children-list"><?php
													foreach ( $services as $service ) {
														$is_connected       = $location->is_new_record() ? true : $service->has_agent_and_location( $agent->id, $location->id );
														$is_connected_value = $is_connected ? 'yes' : 'no'; ?>
                                                        <li class="<?php echo $is_connected ? 'active' : ''; ?>">
															<?php echo OsFormHelper::hidden_field( 'location[agents][agent_' . $agent->id . '][service_' . $service->id . '][connected]', $is_connected_value, array( 'class' => 'connection-child-is-connected' ) ); ?>
															<?php echo $service->name; ?>
                                                        </li>
													<?php } ?>
                                                </ul>
                                                </div><?php
											} ?>
                                            </div><?php
										} else {
											// one service
											$service            = $services[0];
											$is_connected       = $location->is_new_record() ? true : $service->has_agent_and_location( $agent->id, $location->id );
											$is_connected_value = $is_connected ? 'yes' : 'no';
											?>
                                            <div class="connection <?php echo $is_connected ? 'active' : ''; ?>">
                                                <div class="connection-i selector-trigger">
                                                    <div class="connection-avatar"><img src="<?php echo $agent->get_avatar_url(); ?>"/></div>
                                                    <h3 class="connection-name"><?php echo esc_html( $agent->full_name ); ?></h3>
													<?php echo OsFormHelper::hidden_field( 'location[agents][agent_' . $agent->id . '][service_' . $service->id . '][connected]', $is_connected_value, array( 'class' => 'connection-child-is-connected' ) ); ?>
                                                </div>
                                            </div>
											<?php
										}
									}
								}
							} else { ?>
                                <div class="no-results-w">
                                    <div class="icon-w"><i class="latepoint-icon latepoint-icon-users"></i></div>
                                    <h2><?php _e( 'No Existing Agents Found', 'latepoint-pro-features' ); ?></h2>
                                    <a href="<?php echo OsRouterHelper::build_link( [ 'agents', 'new_form' ] ) ?>" class="latepoint-btn"><i
                                                class="latepoint-icon latepoint-icon-plus"></i><span><?php _e( 'Add First Agent', 'latepoint-pro-features' ); ?></span></a>
                                </div> <?php
							}
							?>
                        </div>
                    </div>
                </div>
			<?php } ?>
			<?php if ( OsRolesHelper::can_user( 'resource_schedule__edit' ) ) { ?>

                <div class="white-box section-anchor" id="stickySectionSchedule">
                    <div class="white-box-header">
                        <div class="os-form-sub-header">
                            <h3><?php _e( 'Location Schedule', 'latepoint-pro-features' ); ?></h3>
                            <div class="os-form-sub-header-actions">
								<?php echo OsFormHelper::checkbox_field( 'is_custom_schedule', __( 'Set Custom Schedule', 'latepoint-pro-features' ), 'on', $is_custom_schedule, array( 'data-toggle-element' => '.custom-schedule-wrapper' ) ); ?>
                            </div>
                        </div>
                    </div>
                    <div class="white-box-content">
                        <div class="custom-schedule-wrapper" style="<?php if ( ! $is_custom_schedule ) {
							echo 'display: none;';
						} ?>">
							<?php
							$filter = new \LatePoint\Misc\Filter( [ 'exact_match' => true ] );
							if ( ! $location->is_new_record() ) {
								$filter->location_id = $location->id;
							} ?>
							<?php OsWorkPeriodsHelper::generate_work_periods( $custom_work_periods, $filter, $location->is_new_record() ); ?>
                        </div>
                        <div class="custom-schedule-wrapper" style="<?php if ( $is_custom_schedule ) {
							echo 'display: none;';
						} ?>">
                            <div class="latepoint-message latepoint-message-subtle"><?php _e( 'This location is using general schedule which is set in main settings', 'latepoint-pro-features' ); ?></div>
                        </div>
                    </div>
                </div>

				<?php if ( ! $location->is_new_record() ) { ?>

                    <div class="white-box section-anchor" id="stickySectionCustomScheduleDays">
                        <div class="white-box-header">
                            <div class="os-form-sub-header"><h3><?php _e( 'Days With Custom Schedules', 'latepoint-pro-features' ); ?></h3></div>
                        </div>
                        <div class="white-box-content">
                            <div class="latepoint-message latepoint-message-subtle"><?php _e( 'Location shares custom daily schedules that you set in general settings for your company, however you can add additional days with custom hours which will be specific to this location only.', 'latepoint-pro-features' ); ?></div>
							<?php OsWorkPeriodsHelper::generate_days_with_custom_schedule( [ 'location_id' => $location->id ] ); ?>
                        </div>
                    </div>
                    <div class="white-box section-anchor" id="stickySectionHolidays">
                        <div class="white-box-header">
                            <div class="os-form-sub-header"><h3><?php _e( 'Holidays & Days Off', 'latepoint-pro-features' ); ?></h3></div>
                        </div>
                        <div class="white-box-content">
                            <div class="latepoint-message latepoint-message-subtle"><?php _e( 'Location uses the same holidays you set in general settings for your company, however you can add additional holidays for this location here.', 'latepoint-pro-features' ); ?></div>
							<?php OsWorkPeriodsHelper::generate_off_days( [ 'location_id' => $location->id ] ); ?>
                        </div>
                    </div>
				<?php } ?>
			<?php } ?>
            <div class="os-form-buttons os-flex hidden-with-side-nav">
				<?php
				$extra_actions_html = '';
				if ( $location->is_new_record() ) {
					echo OsFormHelper::hidden_field( 'location[id]', '' );
					echo OsFormHelper::button( 'submit', __( 'Save Location', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] );
				} else {
					echo OsFormHelper::hidden_field( 'location[id]', $location->id );

					if ( OsRolesHelper::can_user( 'location__delete' ) || OsRolesHelper::can_user( 'location__edit' ) ) {
						$extra_actions_html .= '<div class="os-trigger-dots"><div class="os-trigger-dots-context">';
						if ( OsRolesHelper::can_user( 'location__delete' ) ) {
							$extra_actions_html .= '<div class="os-context-item os-danger" 
							data-os-prompt="' . __( 'Are you sure you want to remove this location? It will remove all appointments associated with it. If you only want to temprorary disable it - it is better to just change status to disabled.', 'latepoint-pro-features' ) . '" 
                data-os-redirect-to="' . OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'locations', 'index' ) ) . '" 
                data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $location->id ], 'destroy_location_' . $location->id ) . '" 
                data-os-success-action="redirect" 
                data-os-action="' . OsRouterHelper::build_route_name( 'locations', 'destroy' ) . '"><i class="latepoint-icon latepoint-icon-trash-2"></i><span>' . __( 'Delete', 'latepoint-pro-features' ) . '</span></div>';
						}
						if ( OsRolesHelper::can_user( 'location__edit' ) ) {
							$extra_actions_html .= '<div class="os-context-item"
                        data-os-prompt="' . __( 'Are you sure you want to duplicate this location?', 'latepoint-pro-features' ) . '"
                        data-os-success-action="redirect" 
                        data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $location->id ], 'duplicate_location_' . $location->id ) . '" 
                        data-os-action="' . OsRouterHelper::build_route_name( 'locations', 'duplicate' ) . '"><i class="latepoint-icon latepoint-icon-copy"></i><span>' . __( 'Duplicate', 'latepoint-pro-features' ) . '</span></div>';

						}
						$extra_actions_html .= '</div><i class="latepoint-icon latepoint-icon-more-horizontal"></i></div>';
						echo $extra_actions_html;
						if ( OsRolesHelper::can_user( 'location__edit' ) ) {
							echo OsFormHelper::button( 'submit', __( 'Save Changes', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] );
						}
					}

				}

				?>
            </div>
			<?php wp_nonce_field( $location->is_new_record() ? 'new_location' : 'edit_location_' . $location->id ); ?>
        </div>
        <div class="latepoint-page-side-nav">
            <?php if ( OsRolesHelper::can_user( 'location__edit' ) ) { ?>
                <div class="side-nav-actions">
                    <?php echo $extra_actions_html; ?>
                    <button type="submit" class="latepoint-btn latepoint-btn-block"><i class="latepoint-icon latepoint-icon-check"></i><span><?php _e( 'Save Changes', 'latepoint-pro-features' ); ?></span></button>
                </div>
            <?php } ?>
            <div class="side-nav-body">
                <div><a href="#stickySectionGeneral" class="is-active"><?php esc_html_e( 'General', 'latepoint-pro-features' ); ?></a></div>
                <div><a href="#stickySectionAgents"><?php esc_html_e( 'Agents', 'latepoint-pro-features' ); ?></a></div>
                <?php if ( OsRolesHelper::can_user( 'resource_schedule__edit' ) ) { ?>
                    <div><a href="#stickySectionSchedule"><?php esc_html_e( 'Schedule', 'latepoint-pro-features' ); ?></a></div>

                    <?php if ( ! $location->is_new_record() ) { ?>
                        <div><a href="#stickySectionCustomScheduleDays"><?php esc_html_e( 'Custom Days', 'latepoint-pro-features' ); ?></a></div>
                        <div><a href="#stickySectionHolidays"><?php esc_html_e( 'Holidays & Days off', 'latepoint-pro-features' ); ?></a></div>
                    <?php } ?>
				<?php } ?>
				<?php

				/**
				 * Sticky menu items links for the location edit form
				 *
				 * @param {array} $sticky_menu_items items that go into sticky menu on the right of settings, in format ['href' => '', 'label' => '']
				 * @returns {array} The filtered array of sticky menu items
				 *
				 * @since 5.2.0
				 * @hook latepoint_location_edit_form_sticky_section_items
				 *
				 */
				$before_other_items = apply_filters( 'latepoint_location_edit_form_sticky_section_items', [] );
				foreach ( $before_other_items as $item ) {
					echo '<div><a href="#' . esc_attr( $item['href'] ) . '">' . esc_html( $item['label'] ) . '</a></div>';
				}
				?>
            </div>
        </div>
    </div>
</form>
