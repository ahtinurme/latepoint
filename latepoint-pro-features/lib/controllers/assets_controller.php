<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsAssetsController' ) ) :

	class OsAssetsController extends OsController {

		public function __construct() {
			parent::__construct();

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/assets/';
			$this->vars['page_header']   = OsMenuHelper::get_menu_items_by_id( 'assets' );
			$this->vars['breadcrumbs'][] = [
				'label' => __( 'Assets', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'assets', 'index' ) ),
			];
		}

		public function index() {
			$service = new OsServiceModel();
			$agent   = new OsAgentModel();

			$assets = new OsAssetModel();
			$assets = $assets->order_by( 'name asc' )->get_results_as_models();

			$this->vars['total_services'] = $service->count();
			$this->vars['total_agents']   = $agent->count();
			$this->vars['assets']         = $assets;
			$this->format_render( __FUNCTION__ );
		}

		public function new_form() {
			$this->vars['page_header']   = __( 'New Asset', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = [
				'label' => __( 'New Asset', 'latepoint-pro-features' ),
				'link'  => false,
			];

			$asset    = new OsAssetModel();
			$services = new OsServiceModel();
			$agents   = new OsAgentModel();

			$this->vars['asset']    = $asset;
			$this->vars['services'] = $services->get_results_as_models();
			$this->vars['agents']   = $agents->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}

		public function edit_form() {
			$asset_id                    = $this->params['id'];
			$this->vars['page_header']   = __( 'Edit Asset', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = [
				'label' => __( 'Edit Asset', 'latepoint-pro-features' ),
				'link'  => false,
			];

			$asset    = new OsAssetModel( $asset_id );
			$services = new OsServiceModel();
			$agents   = new OsAgentModel();

			$this->vars['asset']    = $asset;
			$this->vars['services'] = $services->get_results_as_models();
			$this->vars['agents']   = $agents->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}

		public function create() {
			$this->update();
		}

		public function update() {
			$is_new_record       = ( isset( $this->params['asset']['id'] ) && $this->params['asset']['id'] ) ? false : true;
			$asset               = new OsAssetModel();
			$extra_response_vars = [];

			$this->check_nonce( $is_new_record ? 'new_asset' : 'edit_asset_' . $this->params['asset']['id'] );

			$asset->set_data( $this->params['asset'] );

			if ( $asset->save() && $asset->save_connected_services( $this->params['asset']['services'] ?? [] ) && $asset->save_connected_agents( $this->params['asset']['agents'] ?? [] ) ) {
				if ( $is_new_record ) {
					$response_html = __( 'Asset Created. ID:', 'latepoint-pro-features' ) . $asset->id;
				} else {
					$response_html = __( 'Asset Updated. ID:', 'latepoint-pro-features' ) . $asset->id;
				}
				$status                           = LATEPOINT_STATUS_SUCCESS;
				$extra_response_vars['record_id'] = $asset->id;
			} else {
				$response_html = $asset->get_error_messages();
				$status        = LATEPOINT_STATUS_ERROR;
			}

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					[
						'status'  => $status,
						'message' => $response_html,
					] + $extra_response_vars
				);
			}
		}

		public function destroy() {
			$this->check_nonce( 'destroy_asset_' . $this->params['id'] );
			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$asset = new OsAssetModel( $this->params['id'] );
				if ( $asset->delete() ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Asset Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Asset', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Asset', 'latepoint-pro-features' );
			}

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					[
						'status'  => $status,
						'message' => $response_html,
					]
				);
			}
		}
	}

endif;
