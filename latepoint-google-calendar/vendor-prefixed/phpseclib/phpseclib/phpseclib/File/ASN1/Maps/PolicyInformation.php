<?php

/**
 * PolicyInformation
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
 * PolicyInformation
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class PolicyInformation
{
    const MAP = [
        'type' => ASN1::TYPE_SEQUENCE,
        'children' => [
            'policyIdentifier' => CertPolicyId::MAP,
            'policyQualifiers' => [
                'type' => ASN1::TYPE_SEQUENCE,
                'min' => 0,
                'max' => -1,
                'optional' => true,
                'children' => PolicyQualifierInfo::MAP
            ]
        ]
    ];
}
