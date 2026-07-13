<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsJoogaController')) :

    class OsJoogaController extends OsController {

        public function __construct() {
            parent::__construct();
            $this->views_folder          = plugin_dir_path(__FILE__) . '../views/jooga/';
            $this->vars['page_header']   = 'Jooga rühmatreeningu ajad';
            $this->vars['breadcrumbs'][] = ['label' => 'Jooga graafik', 'link' => false];
        }

        public function index() {
            $raw = (string) get_option('yumefit_jooga_slots', '');
            [, $bad] = yumefit_jooga_parse_slots($raw);

            $this->vars['slots_raw'] = $raw;
            $this->vars['bad']       = $bad;
            $this->vars['saved']     = !empty($this->params['saved']);

            $this->format_render('index');
        }

        public function save() {
            $this->check_nonce('save_jooga_slots');

            $raw = (string) ($this->params['slots'] ?? '');
            [$slots] = yumefit_jooga_parse_slots($raw);
            update_option('yumefit_jooga_slots', $raw);
            yumefit_jooga_rebuild_work_periods($slots);

            wp_safe_redirect(OsRouterHelper::build_link(['jooga', 'index'], ['saved' => 1]));
            exit;
        }
    }

endif;
