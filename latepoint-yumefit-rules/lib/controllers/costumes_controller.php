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

            $field_id  = trim((string) get_option('yumefit_costume_field_id', ''));
            $plates_id = trim((string) get_option('yumefit_costume_plates_field_id', ''));
            $from      = current_time('Y-m-d');
            $to        = date('Y-m-d', strtotime($from . ' +' . (self::DAYS_SHOWN - 1) . ' days'));
            $ems_ids   = implode(',', YUMEFIT_EMS_SERVICES);

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT b.start_date d, UPPER(TRIM(COALESCE(ms.meta_value, ''))) size,
                        LOWER(COALESCE(mp.meta_value, '')) plates, COUNT(*) c
                 FROM {$P}latepoint_bookings b
                 LEFT JOIN {$P}latepoint_customer_meta ms ON ms.object_id = b.customer_id AND ms.meta_key = %s
                 LEFT JOIN {$P}latepoint_customer_meta mp ON mp.object_id = b.customer_id AND mp.meta_key = %s
                 WHERE b.service_id IN ($ems_ids) AND b.status <> %s AND b.start_date BETWEEN %s AND %s
                 GROUP BY d, size, plates",
                $field_id, $plates_id, LATEPOINT_BOOKING_STATUS_CANCELLED, $from, $to
            ));

            // Per day+size the demand splits into: needs plates (ga), needs no
            // plates (ta), no preference (any — can wear either variant).
            $days = [];
            for ($i = 0; $i < self::DAYS_SHOWN; $i++) {
                $days[date('Y-m-d', strtotime($from . " +{$i} days"))] =
                    array_fill_keys(array_merge(YUMEFIT_COSTUME_SIZES, ['?']), ['ga' => 0, 'ta' => 0, 'any' => 0]);
            }
            foreach ($rows as $r) {
                $group = stripos($r->plates, 'dega') !== false ? 'ga' : (stripos($r->plates, 'deta') !== false ? 'ta' : 'any');
                foreach (yumefit_costume_columns($r->size) as $col) {
                    $days[$r->d][$col][$group] += (int) $r->c;
                }
            }

            $raw   = (array) get_option('yumefit_costume_stock', []);
            $stock = [];
            foreach (YUMEFIT_COSTUME_SIZES as $size) {
                $stock[$size] = [
                    'ga' => max(0, (int) ($raw[$size]['ga'] ?? 0)),
                    'ta' => max(0, (int) ($raw[$size]['ta'] ?? 0)),
                ];
            }

            $this->vars['days']          = $days;
            $this->vars['stock']         = $stock;
            $this->vars['saved']         = !empty($this->params['saved']);
            $this->vars['field_missing'] = $field_id === '';

            $this->format_render('index');
        }

        public function save() {
            $this->check_nonce('save_costume_stock');

            $stock = [];
            foreach (YUMEFIT_COSTUME_SIZES as $size) {
                $stock[$size] = [
                    'ga' => max(0, (int) ($this->params['stock'][$size]['ga'] ?? 0)),
                    'ta' => max(0, (int) ($this->params['stock'][$size]['ta'] ?? 0)),
                ];
            }
            update_option('yumefit_costume_stock', $stock, false);

            wp_safe_redirect(OsRouterHelper::build_link(['costumes', 'index'], ['saved' => 1]));
            exit;
        }
    }

endif;
