<?php
/* @var $agent OsAgentModel */
/* @var $services OsServiceModel[] */
/* @var $locations OsLocationModel[] */
/* @var $wp_users_for_select array */
?>
<div class="os-form-w quick-agent-form-w <?php echo ( $agent->is_new_record() ) ? 'is-new-agent' : 'is-existing-agent'; ?>"
     data-refresh-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( 'agents', 'quick_edit' ) ); ?>">
    <form action=""
          data-os-action="<?php echo $agent->is_new_record() ? OsRouterHelper::build_route_name( 'agents', 'create' ) : OsRouterHelper::build_route_name( 'agents', 'update' ); ?>"
          class="agent-quick-edit-form">
        <div class="os-form-header">
	      <?php if($agent->is_new_record()){ ?>
	        <h2><?php esc_html_e('New Agent', 'latepoint-pro-features'); ?></h2>
	      <?php }else{ ?>
	        <h2><?php esc_html_e('Edit Agent', 'latepoint-pro-features'); ?></h2>
	      <?php } ?>
	      <a href="#" class="latepoint-side-panel-close latepoint-side-panel-close-trigger"><i class="latepoint-icon latepoint-icon-x"></i></a>
	    </div>
        <div class="os-form-content">
            <?php if(!$agent->is_new_record()){ ?>
		    <div class="quick-booking-info">
			    <?php echo '<span>'.esc_html__('Agent ID:', 'latepoint-pro-features').'</span><strong>'. esc_html($agent->id).'</strong>'; ?>
			    <?php if (OsAuthHelper::get_current_user()->has_capability('activity__view')) echo '<a href="#" data-agent-id="' . esc_attr($agent->id) . '" data-route="' . esc_attr(OsRouterHelper::build_route_name('agents', 'view_agent_log')) . '" class="quick-agent-form-view-log-btn"><i class="latepoint-icon latepoint-icon-clock"></i>' . esc_html__('History', 'latepoint-pro-features') . '</a>'; ?>
		    </div>
		    <?php } ?>

                <div class="fields-with-avatar">
                    <div class="avatar-column">
                        <div class="avatar-uploader-w">
                            <?php echo OsFormHelper::media_uploader_field('agent[avatar_image_id]', 0, __('Set Avatar', 'latepoint-pro-features'), __('Remove Avatar', 'latepoint-pro-features'), $agent->avatar_image_id, [], [], true, true); ?>
                        </div>
                    </div>
                    <div class="field-column">
                    <?php echo OsFormHelper::text_field('agent[first_name]', __('First Name', 'latepoint-pro-features'), $agent->first_name, ['theme' => 'simple', 'validate' => $agent->get_validations_for_property('first_name')]); ?>
                    </div>
                    <div class="field-column">
                    <?php echo OsFormHelper::text_field('agent[last_name]', __('Last Name', 'latepoint-pro-features'), $agent->last_name, ['theme' => 'simple', 'validate' => $agent->get_validations_for_property('last_name')]); ?>
                    </div>
                </div>

                <div class="os-form-sub-header">
                    <h3><?php _e( 'Contact Info', 'latepoint-pro-features' ); ?></h3>
                </div>
                <div class="os-row">
                    <div class="os-col-lg-12"><?php echo OsFormHelper::text_field( 'agent[email]', __( 'Email Address', 'latepoint-pro-features' ), $agent->email, ['theme' => 'simple'] ); ?></div>
                </div>
                <div class="os-row">
                    <div class="os-col-lg-12"><?php echo OsFormHelper::phone_number_field( 'agent[phone]', __( 'Phone Number', 'latepoint-pro-features' ), $agent->phone, ['theme' => 'simple'] ); ?></div>
                </div>
                <div class="os-row">
                    <div class="os-col-lg-12"><?php echo OsFormHelper::text_field( 'agent[display_name]', __( 'Display Name', 'latepoint-pro-features' ), $agent->display_name, ['theme' => 'simple'] ); ?></div>
                </div>
				<?php if ( OsRolesHelper::can_user( 'settings__edit' ) ) { ?>
                    <div class="os-row">
                        <div class="os-col-6"><?php echo OsFormHelper::select_field( 'agent[wp_user_id]', __( 'Connect to WP User', 'latepoint-pro-features' ), $wp_users_for_select, $agent->wp_user_id, [ 'placeholder' => __( 'Select User', 'latepoint-pro-features' ) ] ); ?></div>
                        <div class="os-col-6"><?php echo OsFormHelper::select_field( 'agent[status]', __( 'Status', 'latepoint-pro-features' ), array( LATEPOINT_AGENT_STATUS_ACTIVE   => __( 'Active', 'latepoint-pro-features' ),
						                                                                                                                               LATEPOINT_AGENT_STATUS_DISABLED => __( 'Disabled', 'latepoint-pro-features' )
							), $agent->status ); ?></div>
                    </div>
				<?php } ?>
                <div class="os-form-sub-header">
                    <h3><?php _e( 'Additional Contacts', 'latepoint-pro-features' ); ?></h3>
                </div>
                <div class="latepoint-message latepoint-message-subtle"><?php _e( 'If you need to notify multiple persons about the appointment, you can list additional email addresses and phone numbers to send notification emails and sms to. You can list multiple numbers and emails separated by commas.', 'latepoint-pro-features' ); ?></div>
                <div class="os-row">
                    <div class="os-col-lg-12"><?php echo OsFormHelper::text_field( 'agent[extra_emails]', __( 'Additional Email Addresses', 'latepoint-pro-features' ), $agent->extra_emails, ['theme' => 'simple'] ); ?></div>
                </div>
                <div class="os-row">
                    <div class="os-col-lg-12"><?php echo OsFormHelper::text_field( 'agent[extra_phones]', __( 'Additional Phone Numbers', 'latepoint-pro-features' ), $agent->extra_phones, ['theme' => 'simple'] ); ?></div>
                </div>
                <div class="os-form-sub-header">
                    <h3><?php _e( 'Extra Information', 'latepoint-pro-features' ); ?></h3>
                </div>

            <div class="os-mb-2">
				<?php echo OsFormHelper::media_uploader_field( 'agent[bio_image_id]', 0, __( 'Set Bio Image', 'latepoint-pro-features' ), __( 'Remove Bio Image', 'latepoint-pro-features' ), $agent->bio_image_id ); ?>
                </div>
				<?php echo OsFormHelper::text_field( 'agent[title]', __( 'Agent Title', 'latepoint-pro-features' ), $agent->title, ['theme' => 'simple'] ); ?>
				<?php echo OsFormHelper::textarea_field( 'agent[bio]', __( 'Bio Text', 'latepoint-pro-features' ), $agent->bio, [ 'rows' => 5, 'theme' => 'simple' ] ); ?>
                <div class="os-form-sub-header">
                    <h3><?php _e( 'Agent Highlights', 'latepoint-pro-features' ) ?></h3>
                </div>
                <div class="latepoint-message latepoint-message-subtle"><?php _e( 'These value-label pairs will appear on agent information popup. You can enter things like years of experience, or number of clients served, to highlight agent accomplishments.', 'latepoint-pro-features' ); ?></div>
                <div class="os-agent-highlights-compact">
					<?php for ( $i = 0; $i < 3; $i ++ ) {
						$feature_value = isset( $agent->features_arr[ $i ] ) ? $agent->features_arr[ $i ]['value'] : '';
						$feature_label = isset( $agent->features_arr[ $i ] ) ? $agent->features_arr[ $i ]['label'] : ''; ?>
                        <div class="os-agent-highlight-compact">
                            <h4><?php echo ( $i + 1 ); ?></h4>
                            <div class="os-agent-highlight-fields">
								<?php echo OsFormHelper::text_field( 'agent[features][' . $i . '][value]', __( 'Value', 'latepoint-pro-features' ), $feature_value, ['theme' => 'simple'] ); ?>
								<?php echo OsFormHelper::text_field( 'agent[features][' . $i . '][label]', __( 'Label', 'latepoint-pro-features' ), $feature_label, ['theme' => 'simple'] ); ?>
                            </div>
                        </div>
					<?php } ?>
                </div>
		<?php if ( OsRolesHelper::can_user( 'connection__edit' ) ) { ?>
                    <div class="os-form-sub-header">
                        <h3><?php _e( 'Offered Services', 'latepoint-pro-features' ); ?></h3>
                        <div class="os-form-sub-header-actions">
							<?php echo OsFormHelper::checkbox_field( 'select_all_services', __( 'Select All', 'latepoint-pro-features' ), 'on', $agent->is_new_record(), [ 'class' => 'os-select-all-toggler' ] ); ?>
                        </div>
                    </div>
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
		<?php } ?>
		<?php if ( OsRolesHelper::can_user( 'resource_schedule__edit' ) ) { ?>
                    <div class="os-form-sub-header">
                        <h3><?php _e( 'Agent Schedule', 'latepoint-pro-features' ); ?></h3>
                        <div class="os-form-sub-header-actions">
							<?php echo OsFormHelper::checkbox_field( 'is_custom_schedule', __( 'Set Custom Schedule', 'latepoint-pro-features' ), 'on', $is_custom_schedule, array( 'data-toggle-element' => '.custom-schedule-wrapper' ) ); ?>
                        </div>
                    </div>
                    <div class="custom-schedule-wrapper" style="<?php if ( ! $is_custom_schedule ) {
						echo 'display: none;';
					} ?>">
						<?php
						$filter = new \LatePoint\Misc\Filter();
						if ( ! $agent->is_new_record() ) {
							$filter->agent_id = $agent->id;
						} ?>
						<?php OsWorkPeriodsHelper::generate_work_periods( $custom_work_periods, $filter, $agent->is_new_record() ); ?>
                    </div>
                    <div class="custom-schedule-wrapper" style="<?php if ( $is_custom_schedule ) {
						echo 'display: none;';
					} ?>">
                        <div class="latepoint-message latepoint-message-subtle"><?php _e( 'This agent is using general schedule which is set in main settings', 'latepoint-pro-features' ); ?></div>
                    </div>

			<?php if ( ! $agent->is_new_record() ) { ?>


                        <div class="os-form-sub-header"><h3><?php _e( 'Days With Custom Schedules', 'latepoint-pro-features' ); ?></h3></div>
                        <div class="latepoint-message latepoint-message-subtle"><?php _e( 'Agent shares custom daily schedules that you set in general settings for your company, however you can add additional days with custom hours which will be specific to this agent only.', 'latepoint-pro-features' ); ?></div>
						<?php OsWorkPeriodsHelper::generate_days_with_custom_schedule( [ 'agent_id' => $agent->id ] ); ?>
                        <div class="os-form-sub-header"><h3><?php _e( 'Holidays & Days Off', 'latepoint-pro-features' ); ?></h3></div>
                        <div class="latepoint-message latepoint-message-subtle"><?php _e( 'Agent uses the same holidays you set in general settings for your company, however you can add additional holidays for this agent here.', 'latepoint-pro-features' ); ?></div>
						<?php OsWorkPeriodsHelper::generate_off_days( [ 'agent_id' => $agent->id ] ); ?>
			<?php } ?>
		<?php } ?>
		<?php do_action( 'latepoint_agent_form', $agent ); ?>
        </div>
        <div class="os-form-buttons os-quick-form-buttons">
			<?php
			if ( $agent->is_new_record() ) {
				echo OsFormHelper::hidden_field( 'agent[id]', '' );
				echo OsFormHelper::button( 'submit', __( 'Add Agent', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn latepoint-btn-block latepoint-btn-lg' ] );
			} else {
				echo OsFormHelper::hidden_field( 'agent[id]', $agent->id );
				if ( OsRolesHelper::can_user( 'agent__edit' ) ) {
					echo OsFormHelper::button( 'submit', __( 'Save Changes', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn latepoint-btn-block latepoint-btn-lg' ] );
				}
				if ( OsRolesHelper::can_user( 'agent__delete' ) ) {
					echo '<a href="#" class="latepoint-btn latepoint-btn-secondary latepoint-btn-lg latepoint-btn-just-icon remove-agent-btn" style="margin-left: auto;" 
				        data-os-prompt="' . __( 'Are you sure you want to remove this agent?', 'latepoint-pro-features' ) . '" 
				        data-os-redirect-to="' . OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'agents', 'index' ) ) . '" 
				        data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $agent->id ], 'destroy_agent_' . $agent->id ) . '" 
				        data-os-success-action="redirect" 
				        data-os-action="' . OsRouterHelper::build_route_name( 'agents', 'destroy' ) . '"><i class="latepoint-icon latepoint-icon-trash1"></i></a>';
				}

			}
			?>
        </div>
		<?php wp_nonce_field( $agent->is_new_record() ? 'new_agent' : 'edit_agent_' . $agent->id ); ?>
    </form>
</div>