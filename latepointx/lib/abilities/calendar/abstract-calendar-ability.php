<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractCalendarAbility extends LatePointAbstractAbility {

	protected function serialize_off_period( OsOffPeriodModel $p ): array {
		return [
			'id'         => (int) $p->id,
			'name'       => $p->summary ?? '',
			'start_date' => $p->start_date ?? '',
			'end_date'   => $p->end_date ?? '',
			'agent_id'   => (int) $p->agent_id,
		];
	}

	protected function serialize_work_period( OsWorkPeriodModel $w ): array {
		return [
			'id'         => (int) $w->id,
			'weekday'    => (int) $w->week_day,
			'start_time' => (int) $w->start_time,
			'end_time'   => (int) $w->end_time,
			'agent_id'   => (int) $w->agent_id,
		];
	}

	protected function off_period_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer' ],
				'name'       => [ 'type' => 'string' ],
				'start_date' => [ 'type' => 'string' ],
				'end_date'   => [ 'type' => 'string' ],
				'agent_id'   => [ 'type' => 'integer' ],
			],
		];
	}
}
