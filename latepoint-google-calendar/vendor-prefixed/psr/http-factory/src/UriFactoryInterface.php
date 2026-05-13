<?php
/**
 * @license MIT
 *
 * Modified by latepoint on 28-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace LatePoint\GoogleCalendarAddon\Psr\Http\Message;

interface UriFactoryInterface
{
    /**
     * Create a new URI.
     *
     * @param string $uri
     *
     * @return UriInterface
     *
     * @throws \InvalidArgumentException If the given URI cannot be parsed.
     */
    public function createUri(string $uri = ''): UriInterface;
}
