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
            $monday = $this->week_monday($this->params['week_start'] ?? '');
            $dates  = $this->week_dates($monday);

            $agents = (new OsAgentModel())
                ->where(['status' => LATEPOINT_AGENT_STATUS_ACTIVE])
                ->order_by('first_name asc, last_name asc')
                ->get_results_as_models();
            $agents = is_array($agents) ? $agents : [];

            $this->vars['agents']     = $agents;
            $this->vars['dates']      = $dates;
            $this->vars['week_start'] = $monday->format('Y-m-d');
            $this->vars['prev_week']  = (clone $monday)->modify('-7 days')->format('Y-m-d');
            $this->vars['next_week']  = (clone $monday)->modify('+7 days')->format('Y-m-d');
            $this->vars['grid']       = $this->build_grid($agents, $dates);
            $this->vars['saved']      = !empty($this->params['saved']);

            $this->format_render('index');
        }

        public function save() {
            $this->check_nonce('save_availability');

            $submitted   = (isset($this->params['availability']) && is_array($this->params['availability'])) ? $this->params['availability'] : [];
            $default_weekly = $this->weekly_by_day(0);

            foreach ($submitted as $agent_id => $dates) {
                $agent_id = (int) $agent_id;
                if (!$agent_id || !is_array($dates)) {
                    continue;
                }
                $specific_weekly = $this->weekly_by_day($agent_id);
                foreach ($dates as $date => $times) {
                    $day = DateTime::createFromFormat('Y-m-d', $date);
                    if (!$day || $day->format('Y-m-d') !== $date) {
                        continue;
                    }
                    $week_day = (int) $day->format('N');
                    $baseline = $this->baseline_minutes($specific_weekly, $default_weekly, $week_day);
                    $this->save_date($agent_id, $date, $week_day, $times['start'] ?? '', $times['end'] ?? '', $baseline);
                }
            }

            wp_safe_redirect(OsRouterHelper::build_link(['availability', 'index'], [
                'week_start' => OsRouterHelper::get_request_param('week_start', ''),
                'saved'      => 1,
            ]));
            exit;
        }

        /**
         * Persist an agent's availability for one specific date. We only store a dated
         * override when it differs from the agent's recurring weekly schedule; matching
         * the weekly hours removes any override so the date keeps following the schedule.
         */
        private function save_date(int $agent_id, string $date, int $week_day, string $start, string $end, ?array $baseline): void {
            $existing = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => $date,
            ])->get_results_as_models();
            foreach (is_array($existing) ? $existing : [] as $row) {
                $row->delete();
            }

            $submitted = $this->period_minutes($start, $end);

            if ($submitted === $baseline) {
                return;
            }

            $period = new OsWorkPeriodModel();
            $period->set_data([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'week_day'    => $week_day,
                'custom_date' => $date,
                'start_time'  => $submitted ? $submitted[0] : 0,
                'end_time'    => $submitted ? $submitted[1] : 0,
            ]);
            $period->save();
        }

        /**
         * @param OsAgentModel[]    $agents
         * @param array<int, array> $dates  list of ['date' => 'Y-m-d', 'week_day' => int, ...]
         */
        private function build_grid(array $agents, array $dates): array {
            $default_weekly = $this->weekly_by_day(0);
            $grid           = [];
            foreach ($agents as $agent) {
                $specific_weekly = $this->weekly_by_day($agent->id);
                foreach ($dates as $d) {
                    $custom   = $this->periods_for_date($agent->id, $d['date']);
                    $baseline = $this->baseline_periods($specific_weekly, $default_weekly, $d['week_day']);
                    $grid[$agent->id][$d['date']] = $this->build_cell($custom, $baseline);
                }
            }
            return $grid;
        }

        /**
         * @param OsWorkPeriodModel[] $custom    dated periods for this exact date
         * @param OsWorkPeriodModel[] $baseline  recurring weekly periods for this weekday
         * @return array{state:string, start:string, end:string, inherited:bool, periods:string[]}
         */
        private function build_cell(array $custom, array $baseline): array {
            $inherited = empty($custom);
            $source    = $inherited ? $baseline : $custom;
            $active    = array_values(array_filter($source, fn($p) => $p->start_time != $p->end_time));

            if (count($active) > 1) {
                return [
                    'state'     => 'locked',
                    'start'     => '',
                    'end'       => '',
                    'inherited' => $inherited,
                    'periods'   => array_map(fn($p) => $this->m2hm($p->start_time) . '–' . $this->m2hm($p->end_time), $active),
                ];
            }

            $row = $active[0] ?? null;
            return [
                'state'     => 'editable',
                'start'     => $row ? $this->m2hm($row->start_time) : '',
                'end'       => $row ? $this->m2hm($row->end_time) : '',
                'inherited' => $inherited,
                'periods'   => [],
            ];
        }

        /**
         * Recurring weekly periods for a weekday: the agent's own if set, otherwise the
         * global default schedule (agent_id 0). Mirrors how LatePoint resolves availability.
         *
         * @param array<int, OsWorkPeriodModel[]> $specific_weekly
         * @param array<int, OsWorkPeriodModel[]> $default_weekly
         * @return OsWorkPeriodModel[]
         */
        private function baseline_periods(array $specific_weekly, array $default_weekly, int $week_day): array {
            if (isset($specific_weekly[$week_day])) {
                return $specific_weekly[$week_day];
            }
            return $default_weekly[$week_day] ?? [];
        }

        /** @return ?array{0:int, 1:int} weekly start/end minutes, or null for a day off */
        private function baseline_minutes(array $specific_weekly, array $default_weekly, int $week_day): ?array {
            $periods = $this->baseline_periods($specific_weekly, $default_weekly, $week_day);
            $active  = array_values(array_filter($periods, fn($p) => $p->start_time != $p->end_time));
            if (count($active) !== 1) {
                return null;
            }
            return [(int) $active[0]->start_time, (int) $active[0]->end_time];
        }

        /** @return array<int, OsWorkPeriodModel[]> recurring weekly periods grouped by week_day */
        private function weekly_by_day(int $agent_id): array {
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

        /** @return OsWorkPeriodModel[] dated periods for one exact date */
        private function periods_for_date(int $agent_id, string $date): array {
            $rows = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => $date,
            ])->order_by('start_time asc')->get_results_as_models();
            return is_array($rows) ? $rows : [];
        }

        private function week_monday(string $week_start): DateTime {
            if ($week_start && DateTime::createFromFormat('Y-m-d', $week_start)) {
                $date = DateTime::createFromFormat('Y-m-d', $week_start);
            } else {
                $date = new DateTime(OsTimeHelper::today_date());
            }
            $date->setTime(0, 0);
            return $date->modify('monday this week');
        }

        /** @return array<int, array{date:string, week_day:int, day_name:string, day_number:string}> */
        private function week_dates(DateTime $monday): array {
            $dates = [];
            for ($i = 0; $i < 7; $i++) {
                $day     = (clone $monday)->modify("+{$i} days");
                $dates[] = [
                    'date'       => $day->format('Y-m-d'),
                    'week_day'   => (int) $day->format('N'),
                    'day_name'   => OsBookingHelper::get_weekday_name_by_number((int) $day->format('N'), true),
                    'day_number' => $day->format('j M'),
                ];
            }
            return $dates;
        }

        private function m2hm(int $minutes): string {
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        /** @return ?array{0:int, 1:int} start/end minutes, or null for a day off / invalid */
        private function period_minutes(string $start, string $end): ?array {
            $s = $this->hm_to_minutes($start);
            $e = $this->hm_to_minutes($end);
            if ($s === null || $e === null || $s >= $e) {
                return null;
            }
            return [$s, $e];
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
