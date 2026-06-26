<?php
// Standalone self-check for the amount-label helper (no WordPress needed).
// Run: php test_amount_label.php

function yumefit_giftcard_amount_label($amount): string {
    return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
}

$cases = [
    ['50.0000', '50'],
    [50, '50'],
    ['12.50', '12.5'],
    ['12.55', '12.55'],
    [99.9, '99.9'],
    [5, '5'],
    ['1000.0000', '1000'],
];

foreach ($cases as [$in, $want]) {
    $got = yumefit_giftcard_amount_label($in);
    assert($got === $want, "amount_label($in) = '$got', expected '$want'");
}

echo "ok\n";
