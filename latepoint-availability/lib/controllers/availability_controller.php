<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsAvailabilityController')) :

    class OsAvailabilityController extends OsController {

        public function __construct() {
            parent::__construct();
            $this->views_folder          = plugin_dir_path(__FILE__) . '../views/availability/';
            $this->vars['page_header']   = __('Agent Availability', 'latepoint');
            $this->vars['breadcrumbs'][] = [
                'label' => __('Availability', 'latepoint'),
                'link'  => false,
            ];
        }

        public function index() {
            $agents = (new OsAgentModel())
                ->where(['status' => LATEPOINT_AGENT_STATUS_ACTIVE])
                ->order_by('first_name asc, last_name asc')
                ->get_results_as_models();

            $this->vars['agents']     = is_array($agents) ? $agents : [];
            $this->vars['weekdays']   = range(1, 7);
            $this->vars['grid']       = $this->build_grid($this->vars['agents']);
            $this->vars['saved']      = !empty($this->params['saved']);

            $this->format_render('index');
        }

        public function save() {
            $this->check_nonce('save_availability');

            $submitted = isset($this->params['availability']) && is_array($this->params['availability'])
                ? $this->params['availability']
                : [];

            foreach ($submitted as $agent_id => $days) {
                $agent_id = (int) $agent_id;
                if (!$agent_id || !is_array($days)) {
                    continue;
                }
                foreach ($days as $week_day => $times) {
                    $week_day = (int) $week_day;
                    if ($week_day < 1 || $week_day > 7) {
                        continue;
                    }
                    $this->save_day($agent_id, $week_day, $times['start'] ?? '', $times['end'] ?? '');
                }
            }

            wp_safe_redirect(OsRouterHelper::build_link(['availability', 'index'], ['saved' => 1]));
            exit;
        }

        /**
         * Replace an agent's general weekly schedule for one weekday with a single period.
         * Empty/invalid times store an explicit day off (0/0), overriding the global default.
         */
        private function save_day(int $agent_id, int $week_day, string $start, string $end): void {
            $existing = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'week_day'    => $week_day,
                'custom_date' => 'IS NULL',
            ])->get_results_as_models();
            foreach (is_array($existing) ? $existing : [] as $row) {
                $row->delete();
            }

            $start_min = $this->hm_to_minutes($start);
            $end_min   = $this->hm_to_minutes($end);
            if ($start_min === null || $end_min === null || $start_min >= $end_min) {
                $start_min = 0;
                $end_min   = 0;
            }

            $period = new OsWorkPeriodModel();
            $period->set_data([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'week_day'    => $week_day,
                'start_time'  => $start_min,
                'end_time'    => $end_min,
            ]);
            $period->save();
        }

        /**
         * @param OsAgentModel[] $agents
         * @return array<int, array<int, array{state:string, start:string, end:string, inherited:bool, periods:string[]}>>
         */
        private function build_grid(array $agents): array {
            $defaults = $this->periods_by_day(0);
            $grid     = [];
            foreach ($agents as $agent) {
                $specific = $this->periods_by_day($agent->id);
                foreach (range(1, 7) as $day) {
                    $grid[$agent->id][$day] = $this->build_cell(
                        $specific[$day] ?? [],
                        $defaults[$day] ?? []
                    );
                }
            }
            return $grid;
        }

        /**
         * @param OsWorkPeriodModel[] $specific
         * @param OsWorkPeriodModel[] $defaults
         * @return array{state:string, start:string, end:string, inherited:bool, periods:string[]}
         */
        private function build_cell(array $specific, array $defaults): array {
            $active = array_values(array_filter($specific, fn($p) => $p->start_time != $p->end_time));

            if (count($active) > 1) {
                return [
                    'state'     => 'locked',
                    'start'     => '',
                    'end'       => '',
                    'inherited' => false,
                    'periods'   => array_map(fn($p) => $this->m2hm($p->start_time) . '–' . $this->m2hm($p->end_time), $active),
                ];
            }

            if (!empty($specific)) {
                $row = $active[0] ?? null;
                return [
                    'state'     => 'editable',
                    'start'     => $row ? $this->m2hm($row->start_time) : '',
                    'end'       => $row ? $this->m2hm($row->end_time) : '',
                    'inherited' => false,
                    'periods'   => [],
                ];
            }

            $def = array_values(array_filter($defaults, fn($p) => $p->start_time != $p->end_time))[0] ?? null;
            return [
                'state'     => 'editable',
                'start'     => $def ? $this->m2hm($def->start_time) : '',
                'end'       => $def ? $this->m2hm($def->end_time) : '',
                'inherited' => true,
                'periods'   => [],
            ];
        }

        /** @return array<int, OsWorkPeriodModel[]> */
        private function periods_by_day(int $agent_id): array {
            $rows = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => 'IS NULL',
            ])->order_by('week_day asc, start_time asc')->get_results_as_models();

            $by_day = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $by_day[$row->week_day][] = $row;
            }
            return $by_day;
        }

        private function m2hm(int $minutes): string {
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        private function hm_to_minutes(string $value): ?int {
            if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
                return null;
            }
            $minutes = ((int) $m[1] * 60) + (int) $m[2];
            return ($minutes >= 0 && $minutes <= 1440) ? $minutes : null;
        }
    }

endif;
