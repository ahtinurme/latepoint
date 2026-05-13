<?php
/**
 * Plugin Name: LatePoint Addon - Mailchimp
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for Mailchimp
 * Version:     1.0.1
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-mailchimp
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if ( ! class_exists( 'LatePointMailchimp' ) ) :

/**
 * Main Addon Class.
 *
 */

class LatePointMailchimp {

  /**
   * Addon version.
   *
   */
  public $version = '1.0.1';
  public $db_version = '1.0.0';
  public $addon_name = 'latepoint-mailchimp';

  public $marketing_system_code = 'mailchimp';



  /**
   * LatePoint Constructor.
   */
  public function __construct() {
    $this->define_constants();
    $this->init_hooks();
  }

  /**
   * Define LatePoint Constants.
   */
  public function define_constants() {
  }


  public static function public_stylesheets() {
    return plugin_dir_url( __FILE__ ) . 'public/stylesheets/';
  }

  public static function public_javascripts() {
    return plugin_dir_url( __FILE__ ) . 'public/javascripts/';
  }

  public static function images_url() {
    return plugin_dir_url( __FILE__ ) . 'public/images/';
  }

  /**
   * Define constant if not already set.
   *
   */
  public function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }

  /**
   * Include required core files used in admin and on the frontend.
   */
  public function includes() {

		// VENDOR
    include_once( dirname( __FILE__ ) . '/lib/vendor/MailChimp.php' );

		// COMPOSER AUTOLOAD

    // CONTROLLERS
    include_once( dirname( __FILE__ ) . '/lib/controllers/mailchimp_controller.php' );

    // HELPERS
    include_once( dirname( __FILE__ ) . '/lib/helpers/mailchimp_helper.php' );

    // MODELS

  }


  public function init_hooks(){
    add_action('latepoint_init', [$this, 'latepoint_init']);
    add_action('latepoint_includes', [$this, 'includes']);
    add_action('latepoint_admin_enqueue_scripts', [$this, 'load_admin_scripts_and_styles']);

		add_action('latepoint_external_marketing_system_settings', [$this, 'output_marketing_system_settings']);
		add_filter('latepoint_list_of_external_marketing_systems', [$this, 'add_to_list_of_external_marketing_systems'], 10, 3);

    add_filter('latepoint_installed_addons', [$this, 'register_addon']);

    add_filter('latepoint_localized_vars_front', [$this, 'localized_vars_for_front']);
    add_filter('latepoint_localized_vars_admin', [$this, 'localized_vars_for_admin']);

		// Register custom process actions
    add_filter('latepoint_process_action_types', [$this, 'register_add_to_list_action_type']);
    add_filter('latepoint_process_action_names', [$this, 'register_add_to_list_action_name']);

    add_action('latepoint_wp_enqueue_scripts', [$this, 'load_front_scripts_and_styles']);
    add_filter('latepoint_encrypted_settings', [$this, 'add_encrypted_settings']);


	  add_filter('latepoint_activity_codes', [$this, 'add_mailchimp_codes']);
	  add_filter('latepoint_activity_view_vars', [$this, 'add_mailchimp_activity_view'], 10, 2);


		// Add settings to process action
	  add_filter('latepoint_process_action_settings_fields_html_after', [$this, 'add_mailchimp_action_settings'], 10, 2);
	  add_filter('latepoint_process_action_generate_preview', [$this, 'generate_mailchimp_action_preview'], 10, 2);

		// Run mailchimp process action
	  add_filter('latepoint_process_action_run', [$this, 'process_mailchimp_action'], 10, 2);
	  add_filter('latepoint_process_prepare_data_for_run', [$this, 'prepare_data_for_run']);

    // addon specific filters

    add_action( 'init', array( $this, 'init' ), 0 );

    register_activation_hook(__FILE__, [$this, 'on_activate']);
    register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
  }



	public function add_mailchimp_activity_view($vars, OsActivityModel $activity){
		if($activity->code != 'mailchimp_contact_added_to_list') return $vars;
		$data = json_decode($activity->description, true);
		$list_id = $data['to'] ?? 'n/a';
		$vars['name'] = __('Added contact to Mailchimp list', 'latepoint-mailchimp');
		$vars['meta_html'] = '<div class="activity-preview-to"><span class="os-label">' . __('List ID:', 'latepoint-mailchimp') . '</span><span class="os-value">' . esc_html($list_id) . '</div>';
		$vars['content_html'] = '<pre class="format-json">' . $activity->description . '</pre>';
		return $vars;
	}

	public function add_mailchimp_codes($codes){
		$codes['mailchimp_contact_added_to_list'] = __('Added contact to Mailchimp list', 'latepoint-mailchimp');
		return $codes;
	}

	public function prepare_data_for_run(\LatePoint\Misc\ProcessAction $action){
		if($action->type != 'add_to_mailchimp_list') return $action;
		$content = [];

		$action->prepared_data_for_run['list_id'] = esc_html(\OsReplacerHelper::replace_all_vars($action->settings['list_id'], $action->replacement_vars));
		$action->prepared_data_for_run['email'] = esc_html(\OsReplacerHelper::replace_all_vars($action->settings['email'], $action->replacement_vars));
		$action->prepared_data_for_run['first_name'] = esc_html(\OsReplacerHelper::replace_all_vars($action->settings['first_name'], $action->replacement_vars));
		$action->prepared_data_for_run['last_name'] = esc_html(\OsReplacerHelper::replace_all_vars($action->settings['last_name'], $action->replacement_vars));
		return $action;
	}

	public function process_mailchimp_action(array $result, \LatePoint\Misc\ProcessAction $action): array{
		if($action->type != 'add_to_mailchimp_list') return $result;

		$result = OsMailchimpHelper::add_contact_to_list($action->prepared_data_for_run['list_id'], $action->prepared_data_for_run['email'], $action->prepared_data_for_run['first_name'], $action->prepared_data_for_run['last_name'], $action->prepared_data_for_run['activity_data']);
		return $result;
	}

	public function generate_mailchimp_action_preview(string $html, \LatePoint\Misc\ProcessAction $action): string{
		if($action->type != 'add_to_mailchimp_list') return $html;
		$list_id = empty($action->prepared_data_for_run['list_id']) ? __('Not Selected', 'latepoint-mailchimp') : $action->prepared_data_for_run['list_id'];
		$html.= '<div class="action-preview-to"><span class="os-label">'.__('List ID:', 'latepoint-mailchimp').'</span>'.$list_id.'</div>';
		$html.= '<pre class="format-json">'.json_encode(['email' => $action->prepared_data_for_run['email'], 'first_name' => $action->prepared_data_for_run['first_name'], 'last_name' => $action->prepared_data_for_run['last_name']], JSON_PRETTY_PRINT).'</pre>';
		return $html;
	}

	public function add_mailchimp_action_settings(string $html, \LatePoint\Misc\ProcessAction $action): string{
		if($action->type == 'add_to_mailchimp_list'){
			$html = '<div class="process-action-controls-wrapper">';
			$html.= '<a href="#" class="latepoint-btn latepoint-btn-outline latepoint-btn-sm open-template-variables-panel"><i class="latepoint-icon latepoint-icon-zap"></i><span>'.__('Show smart variables', 'latepoint-mailchimp').'</span></a>';
			$html.= '</div>';
			$html.= '<div class="os-row">';
			$html.= \OsFormHelper::select_field('process[actions]['.$action->id.'][settings][list_id]', __('Audience', 'latepoint-mailchimp'), OsMailchimpHelper::get_list_of_audiences(), $action->settings['list_id'], ['theme' => 'bordered', 'placeholder' => __('Select Audience', 'latepoint-mailchimp')], ['class' => 'os-col-6']);
			$html.= \OsFormHelper::text_field('process[actions]['.$action->id.'][settings][email]', __('Email Address', 'latepoint-mailchimp'), $action->settings['email'] ?? '{{customer_email}}', ['theme' => 'simple', 'placeholder' => __('Email Address', 'latepoint-mailchimp')], ['class' => 'os-col-6']);
			$html.= '</div>';
			$html.= '<div class="os-row">';
			$html.= \OsFormHelper::text_field('process[actions]['.$action->id.'][settings][first_name]', __('First Name', 'latepoint-mailchimp'), $action->settings['first_name'] ?? '{{customer_first_name}}', ['theme' => 'simple', 'placeholder' => __('First Name', 'latepoint-mailchimp')], ['class' => 'os-col-6']);
			$html.= \OsFormHelper::text_field('process[actions]['.$action->id.'][settings][last_name]', __('Last Name', 'latepoint-mailchimp'), $action->settings['last_name'] ?? '{{customer_last_name}}', ['theme' => 'simple', 'placeholder' => __('Last Name', 'latepoint-mailchimp')], ['class' => 'os-col-6']);
			$html.= '</div>';
		}
		return $html;
	}

	public function register_add_to_list_action_type($action_types){
		$action_types[] = 'add_to_mailchimp_list';
		return $action_types;
	}

	public function register_add_to_list_action_name($action_names){
		$action_names['add_to_mailchimp_list'] = __('Add contact to Mailchimp', 'latepoint-mailchimp');
		return $action_names;
	}

  public function add_encrypted_settings($encrypted_settings){
    $encrypted_settings[] = 'mailchimp_api_key';
    return $encrypted_settings;
  }


	public function add_to_list_of_external_marketing_systems(array $marketing_systems, bool $enabled_only): array {
		$marketing_systems[] = [
			'code' => $this->marketing_system_code,
			'name' => __('Mailchimp', 'latepoint-mailchimp'),
			'image_url' => $this->images_url().'logo.png',
		];
		return $marketing_systems;
	}


  public function output_marketing_system_settings($marketing_system_code){
    if($marketing_system_code != $this->marketing_system_code) return false;
    if(OsMailchimpHelper::$error) echo '<div class="os-form-message-w status-error">'.OsMailchimpHelper::$error.'</div>';

    ?>
    <div class="sub-section-row">
      <div class="sub-section-label">
        <h3><?php _e('Settings', 'latepoint-mailchimp'); ?></h3>
      </div>
      <div class="sub-section-content">
        <div class="os-row">
          <div class="os-col-12">
            <?php echo OsFormHelper::password_field('settings[mailchimp_api_key]', __('API key', 'latepoint-mailchimp'), OsSettingsHelper::get_settings_value('mailchimp_api_key'), ['theme' => 'simple']); ?>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  /**
   * Init LatePoint when WordPress Initialises.
   */
  public function init() {
    // Set up localisation.
    $this->load_plugin_textdomain();
  }

  public function latepoint_init(){
    if(OsMarketingSystemsHelper::is_external_marketing_system_enabled($this->marketing_system_code)) OsMailchimpHelper::set_api_key();
		LatePoint\Cerber\Router::init_addon();
  }


  public function load_plugin_textdomain() {
    load_plugin_textdomain('latepoint-mailchimp', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }



  public function on_deactivate(){
  }

  public function on_activate(){
    if(class_exists('OsDatabaseHelper')) OsDatabaseHelper::check_db_version_for_addons();
    do_action('latepoint_on_addon_activate', $this->addon_name, $this->version);
  }

  public function register_addon($installed_addons){
    $installed_addons[] = ['name' => $this->addon_name, 'db_version' => $this->db_version, 'version' => $this->version];
    return $installed_addons;
  }




  public function load_front_scripts_and_styles(){
    if(OsMarketingSystemsHelper::is_external_marketing_system_enabled($this->marketing_system_code)){
      // Stylesheets

      // Javascripts
    }

  }

  public function load_admin_scripts_and_styles(){

    // Stylesheets
  }


  public function localized_vars_for_admin($localized_vars){
    return $localized_vars;
  }

  public function localized_vars_for_front($localized_vars){
    return $localized_vars;
  }

}

endif;

if ( in_array( 'latepoint/latepoint.php', get_option( 'active_plugins', array() ) )  || array_key_exists('latepoint/latepoint.php', get_site_option('active_sitewide_plugins', array())) ) {
  $LATEPOINT_ADDON_MAILCHIMP = new LatePointMailchimp();
}
$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
