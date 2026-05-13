<?php
/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

namespace LatePoint\Addons\Taxes;

class Tax {
	public $id;
	public $name  = '';
	public $type  = 'percentage';
	public $value = 0;

	public function __construct( $args = [] ) {
		$allowed_props = self::allowed_props();
		foreach ( $args as $key => $arg ) {
			if ( in_array( $key, $allowed_props ) ) {
				$this->$key = $arg;
			}
		}
		if ( empty( $this->id ) ) {
			$this->id = \OsTaxesHelper::generate_tax_id();
		}
	}

	public function to_save_format() {
		return [
			'id'    => $this->id,
			'name'  => $this->name,
			'type'  => $this->type,
			'value' => $this->value,
		];
	}

	public static function allowed_props(): array {
		return [
			'id',
			'name',
			'type',
			'value',
		];
	}
}
