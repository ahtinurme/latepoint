<?php

/**
 * FieldID
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
 * FieldID
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class FieldID
{
    const MAP = [
        'type' => ASN1::TYPE_SEQUENCE,
        'children' => [
            'fieldType' => ['type' => ASN1::TYPE_OBJECT_IDENTIFIER],
            'parameters' => [
                'type' => ASN1::TYPE_ANY,
                'optional' => true
            ]
        ]
    ];
}
