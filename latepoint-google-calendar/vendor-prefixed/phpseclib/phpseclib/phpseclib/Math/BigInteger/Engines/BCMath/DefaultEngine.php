<?php

/**
 * BCMath Default Modular Exponentiation Engine
 *
 * PHP version 5 and 7
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2017 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://pear.php.net/package/Math_BigInteger
 *
 * Modified by latepoint on 28-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace LatePoint\GoogleCalendarAddon\phpseclib3\Math\BigInteger\Engines\BCMath;

use LatePoint\GoogleCalendarAddon\phpseclib3\Math\BigInteger\Engines\BCMath\Reductions\Barrett;

/**
 * PHP Default Modular Exponentiation Engine
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class DefaultEngine extends Barrett
{
}
