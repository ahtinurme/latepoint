<?php

/**
 * Attribute
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 *
 * Modified by latepoint on 28-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace LatePoint\GoogleCalendarAddon\phpseclib3\File\ASN1\Maps;

use LatePoint\GoogleCalendarAddon\phpseclib3\File\ASN1;

/**
 * Attribute
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class Attribute
{
    const MAP = [
        'type' => ASN1::TYPE_SEQUENCE,
        'children' => [
            'type' => AttributeType::MAP,
            'value' => [
                'type' => ASN1::TYPE_SET,
                'min' => 1,
                'max' => -1,
                'children' => AttributeValue::MAP
            ]
        ]
    ];
}
