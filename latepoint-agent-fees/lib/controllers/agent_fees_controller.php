<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OsAgentFeesController')) :

    class OsAgentFeesController extends OsController {

        public function __construct() {
            parent::__construct();
            $this->views_folder          = plugin_dir_path(__FILE__) . '../views/agent_fees/';
            $this->vars['page_header']   = __('Agent Fees', 'latepoint-agent-fees');
            $this->vars['breadcrumbs'][] = [
                'label' => __('Agent Fees', 'latepoint-agent-fees'),
                'link'  => false,
            ];
        }

        public function index() {
            $first = $this->month_start($this->params['month'] ?? '');
            $last  = (clone $first)->modify('last day of this month');

            $agents = (new OsAgentModel())
                ->where(['status' => LATEPOINT_AGENT_STATUS_ACTIVE])
                ->order_by('first_name asc, last_name asc')
                ->get_results_as_models();
            $agents = is_array($agents) ? $agents : [];

            $fees     = get_option('latepoint_agent_fees', []);
            $sessions = $this->sessions_by_agent_and_date($first->format('Y-m-d'), $last->format('Y-m-d'));

            $stats = [];
            foreach ($agents as $agent) {
                $stats[$agent->id] = $this->month_stats($agent->id, $first, $last, $sessions[$agent->id] ?? []);
            }

            $this->vars['agents']      = $agents;
            $this->vars['fees']        = $fees;
            $this->vars['stats']       = $stats;
            $this->vars['month']       = $first->format('Y-m');
            $this->vars['month_label'] = date_i18n('F Y', $first->getTimestamp());
            $this->vars['prev_month']  = (clone $first)->modify('-1 month')->format('Y-m');
            $this->vars['next_month']  = (clone $first)->modify('+1 month')->format('Y-m');
            $this->vars['saved']       = !empty($this->params['saved']);

            $this->format_render('index');
        }

        public function save_fees() {
            $this->check_nonce('save_agent_fees');

            $fees = [];
            foreach ((array) ($this->params['fees'] ?? []) as $agent_id => $f) {
                $fees[(int) $agent_id] = [
                    'shifts'   => array_map(
                        fn($i) => max(0, (float) ($f['shifts'][$i] ?? 0)),
                        array_keys(LATEPOINT_AGENT_FEES_SHIFTS)
                    ),
                    'training' => max(0, (float) ($f['training'] ?? 0)),
                ];
            }
            update_option('latepoint_agent_fees', $fees);

            wp_safe_redirect(OsRouterHelper::build_link(['agent_fees', 'index'], [
                'month' => $this->params['month'] ?? '',
                'saved' => 1,
            ]));
            exit;
        }

        /**
         * Per-day breakdown and totals for one agent. Days with no working hours and no
         * trainings are skipped so the verification table only shows what was counted.
         *
         * @param array<string, array> $agent_sessions trainings grouped by date
         * @return array{
         *     days: array<int, array>,
         *     schedules: int,
         *     shift_counts: array<int, int>,
         *     trainings: int,
         *     bookings: int
         * }
         */
        private function month_stats(int $agent_id, DateTime $first, DateTime $last, array $agent_sessions): array {
            $default_weekly = $this->weekly_by_day(0);
            $agent_weekly   = $this->weekly_by_day($agent_id);
            $custom         = $this->custom_by_date($agent_id, $first->format('Y-m-d'), $last->format('Y-m-d'));

            $days   = [];
            $totals = [
                'schedules'    => 0,
                'shift_counts' => array_fill(0, count(LATEPOINT_AGENT_FEES_SHIFTS), 0),
                'trainings'    => 0,
                'bookings'     => 0,
            ];

            for ($day = clone $first; $day <= $last; $day->modify('+1 day')) {
                $date     = $day->format('Y-m-d');
                $week_day = (int) $day->format('N');

                $periods = isset($custom[$date]) ?
                    $custom[$date] :
                    ($agent_weekly[$week_day] ?? $default_weekly[$week_day] ?? []);
                $periods = array_values(array_filter($periods, fn($p) => $p[0] < $p[1]));

                $sessions = $agent_sessions[$date] ?? [];
                if (empty($periods) && empty($sessions)) {
                    continue;
                }

                $calc     = latepoint_agent_fees_day_schedules($periods);
                $bookings = array_sum(array_column($sessions, 'bookings'));

                $days[] = [
                    'date'      => $date,
                    'periods'   => array_map(fn($p) => $this->m2hm($p[0]) . '–' . $this->m2hm($p[1]), $periods),
                    'calc'      => $calc,
                    'sessions'  => $sessions,
                ];

                $totals['schedules'] += $calc['schedules'];
                $totals['trainings'] += count($sessions);
                $totals['bookings']  += $bookings;
                foreach ($calc['shift_counts'] as $i => $count) {
                    $totals['shift_counts'][$i] += $count;
                }
            }

            return $totals + ['days' => $days];
        }

        /**
         * Trainings that count towards the fee: happened (approved/completed) and no-show;
         * cancelled excluded. Bookings sharing agent + date + time + service are one group
         * training, counted once.
         *
         * @return array<int, array<string, array<int, array{
         *     time: string,
         *     service_id: int,
         *     bookings: int,
         *     no_show: bool
         * }>>> [agent_id][date] => sessions
         */
        private function sessions_by_agent_and_date(string $from, string $to): array {
            global $wpdb;

            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT agent_id, start_date, start_time, service_id, status FROM ' . LATEPOINT_TABLE_BOOKINGS . '
                 WHERE start_date BETWEEN %s AND %s AND status IN (%s, %s, %s)
                 ORDER BY start_date ASC, start_time ASC',
                $from,
                $to,
                LATEPOINT_BOOKING_STATUS_APPROVED,
                LATEPOINT_BOOKING_STATUS_COMPLETED,
                LATEPOINT_BOOKING_STATUS_NO_SHOW
            ));

            $sessions = [];
            foreach ($rows as $row) {
                $key = "{$row->start_time}-{$row->service_id}";
                $session = &$sessions[(int) $row->agent_id][$row->start_date][$key];
                $session = [
                    'time'       => $this->m2hm((int) $row->start_time),
                    'service_id' => (int) $row->service_id,
                    'bookings'   => ($session['bookings'] ?? 0) + 1,
                    'no_show'    => ($session['no_show'] ?? true) && $row->status === LATEPOINT_BOOKING_STATUS_NO_SHOW,
                ];
                unset($session);
            }

            foreach ($sessions as &$dates) {
                $dates = array_map('array_values', $dates);
            }
            return $sessions;
        }

        /** @return array<int, array<int, array{0:int, 1:int}>> recurring weekly periods by week_day */
        private function weekly_by_day(int $agent_id): array {
            $rows = (new OsWorkPeriodModel())->where([
                'agent_id'    => $agent_id,
                'service_id'  => 0,
                'location_id' => 0,
                'custom_date' => 'IS NULL',
            ])->order_by('week_day asc, start_time asc')->get_results_as_models();

            $by_day = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $by_day[$row->week_day][] = [(int) $row->start_time, (int) $row->end_time];
            }
            return $by_day;
        }

        /**
         * Date-specific overrides within the month. A date present here (even as a lone 0/0
         * "day off" row) fully replaces the recurring weekly schedule, matching LatePoint.
         *
         * @return array<string, array<int, array{0:int, 1:int}>>
         */
        private function custom_by_date(int $agent_id, string $from, string $to): array {
            $rows = (new OsWorkPeriodModel())->where([
                'agent_id'       => $agent_id,
                'service_id'     => 0,
                'location_id'    => 0,
                'custom_date >=' => $from,
                'custom_date <=' => $to,
            ])->order_by('custom_date asc, start_time asc')->get_results_as_models();

            $by_date = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $by_date[$row->custom_date][] = [(int) $row->start_time, (int) $row->end_time];
            }
            return $by_date;
        }

        private function month_start(string $month): DateTime {
            $date = preg_match('/^\d{4}-\d{2}$/', $month) ?
                DateTime::createFromFormat('Y-m-d', "{$month}-01") :
                new DateTime(OsTimeHelper::today_date());
            $date->setTime(0, 0);
            return $date->modify('first day of this month');
        }

        private function m2hm(int $minutes): string {
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
    }

endif;
