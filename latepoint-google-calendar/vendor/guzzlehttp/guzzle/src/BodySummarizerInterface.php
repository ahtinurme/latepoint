<?php

namespace LatePoint\GoogleCalendarAddon\GuzzleHttp;

use LatePoint\GoogleCalendarAddon\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string;
}
