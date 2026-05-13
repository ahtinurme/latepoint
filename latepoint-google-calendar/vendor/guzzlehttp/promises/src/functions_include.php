<?php

namespace LatePoint\GoogleCalendarAddon;

// Don't redefine the functions if included multiple times.
if (!\function_exists('LatePoint\\GoogleCalendarAddon\\GuzzleHttp\\Promise\\promise_for')) {
    require __DIR__ . '/functions.php';
}
