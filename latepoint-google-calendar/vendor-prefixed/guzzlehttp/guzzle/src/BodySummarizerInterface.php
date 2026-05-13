<?php
/**
 * @license MIT
 *
 * Modified by latepoint on 28-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace LatePoint\GoogleCalendarAddon\GuzzleHttp;

use LatePoint\GoogleCalendarAddon\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
