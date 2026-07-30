<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsCostumesController')) :

    class OsCostumesController extends OsController {

        const DAYS_SHOWN = 7;

        public function __construct() {
            parent::__construct();
            $this->views_folder          = plugin_dir_path(__FILE__) . '../views/costumes/';
            $this->vars['page_header']   = 'Kostüümid';
            $this->vars['breadcrumbs'][] = ['label' => 'Kostüümid', 'link' => false];
        }

        public function index() {
            global $wpdb;
            $P = $wpdb->prefix;

            $field_id = trim((string) get_option('yumefit_costume_field_id', ''));
            $from     = current_time('Y-m-d');
            $to       = date('Y-m-d', strtotime($from . ' +' . (self::DAYS_SHOWN - 1) . ' days'));
            $ems_ids  = implode(',', YUMEFIT_EMS_SERVICES);

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT b.start_date d, UPPER(TRIM(COALESCE(m.meta_value, ''))) size, COUNT(*) c
                 FROM {$P}latepoint_bookings b
                 LEFT JOIN {$P}latepoint_customer_meta m ON m.object_id = b.customer_id AND m.meta_key = %s
                 WHERE b.service_id IN ($ems_ids) AND b.status <> %s AND b.start_date BETWEEN %s AND %s
                 GROUP BY d, size",
                $field_id, LATEPOINT_BOOKING_STATUS_CANCELLED, $from, $to
            ));

            $days = [];
            for ($i = 0; $i < self::DAYS_SHOWN; $i++) {
                $days[date('Y-m-d', strtotime($from . " +{$i} days"))] = array_fill_keys(array_merge(YUMEFIT_COSTUME_SIZES, ['?']), 0);
            }
            foreach ($rows as $r) {
                foreach (yumefit_costume_columns($r->size) as $col) {
                    $days[$r->d][$col] += (int) $r->c;
                }
            }

            $this->vars['days']          = $days;
            $this->vars['stock']         = array_map('intval', (array) get_option('yumefit_costume_stock', [])) + array_fill_keys(YUMEFIT_COSTUME_SIZES, 0);
            $this->vars['saved']         = !empty($this->params['saved']);
            $this->vars['field_missing'] = $field_id === '';

            $this->format_render('index');
        }

        public function save() {
            $this->check_nonce('save_costume_stock');

            $stock = [];
            foreach (YUMEFIT_COSTUME_SIZES as $size) {
                $stock[$size] = max(0, (int) ($this->params['stock'][$size] ?? 0));
            }
            update_option('yumefit_costume_stock', $stock, false);

            wp_safe_redirect(OsRouterHelper::build_link(['costumes', 'index'], ['saved' => 1]));
            exit;
        }
    }

endif;
