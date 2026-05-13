<?php

/**
 * EcdsaSigValue
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
 * EcdsaSigValue
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class EcdsaSigValue
{
    const MAP = [
        'type' => ASN1::TYPE_SEQUENCE,
        'children' => [
            'r' => ['type' => ASN1::TYPE_INTEGER],
            's' => ['type' => ASN1::TYPE_INTEGER]
        ]
    ];
}
