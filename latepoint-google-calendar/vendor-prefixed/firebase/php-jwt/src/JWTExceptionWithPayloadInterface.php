<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by latepoint on 28-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */
namespace LatePoint\GoogleCalendarAddon\Firebase\JWT;

interface JWTExceptionWithPayloadInterface
{
    /**
     * Get the payload that caused this exception.
     *
     * @return object
     */
    public function getPayload(): object;

    /**
     * Get the payload that caused this exception.
     *
     * @param object $payload
     * @return void
     */
    public function setPayload(object $payload): void;
}
