#!/usr/bin/env php
<?php

/**
 * Convert a SimplyBook 'clientregister' CSV export into LatePoint-ready
 * customer import files: the main file, ~150-row chunks (to dodge PHP
 * max_execution_time), and a log of any rows that couldn't be converted.
 *
 * Usage:
 *   php convert_clientregister.php [input.csv] [chunk_size]
 *
 * Defaults: input = clientregister_2026-05-13.csv, chunk_size = 150.
 *
 * Outputs (written next to the input file):
 *   latepoint_customers_import.csv          main, comma-delimited UTF-8
 *   latepoint_customers_import_partNN.csv   chunked copies for staged import
 *   latepoint_customers_skipped.csv         rows the converter couldn't use
 *
 * Notes on the placeholder values:
 *   - Empty email  -> noemail+<phone-digits>@<host>.invalid (RFC 2606 TLD,
 *                     guaranteed undeliverable); admin_notes is prefixed
 *                     with a marker so staff can spot it later.
 *   - Empty last_name -> "-"  (LatePoint requires presence)
 *   - Empty phone     -> "00000000"  (8 zeros: required-by-settings AND
 *                     not literal "0", because PHP's empty('0') is true,
 *                     which makes sanitize_phone_number() drop it)
 */

const PLACEHOLDER_TEXT  = '-';
const PLACEHOLDER_PHONE = '00000000';
const PLACEHOLDER_HOST  = 'yumefit.invalid';
const PLACEHOLDER_NOTE  = '[Import: synthetic email — original record had no email]';

const OUT_HEADER = [
    'first_name', 'last_name', 'email', 'phone',
    'street', 'zip', 'city', 'admin_notes',
    'loyalty_note', 'legacy_created_at',
    'email_marketing', 'sms_marketing',
];

$inputPath = $argv[1] ?? 'clientregister_2026-05-13.csv';
$chunkSize = (int) ($argv[2] ?? 150);

if (!is_file($inputPath)) {
    fwrite(STDERR, "Input file not found: {$inputPath}\n");
    exit(1);
}

$inputPath = realpath($inputPath);
$dir       = dirname($inputPath);
$outPath   = $dir . '/latepoint_customers_import.csv';
$skipPath  = $dir . '/latepoint_customers_skipped.csv';

$in   = fopen($inputPath, 'r');
$out  = fopen($outPath, 'w');
$skip = fopen($skipPath, 'w');

if (!$in || !$out || !$skip) {
    fwrite(STDERR, "Could not open input or output files\n");
    exit(1);
}

$rawHeader = fgetcsv($in, 0, ';', '"', '');
if (!$rawHeader) {
    fwrite(STDERR, "Could not read header row\n");
    exit(1);
}
// strip UTF-8 BOM from first column
$rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeader[0]);

$colIndex = array_flip($rawHeader);
$requiredCols = ['Nimi', 'E-mail', 'Telefon', 'Aadress', 'Postiindeks', 'Linn',
                 'Loodud kell', 'Üldteave', 'Püsiklient',
                 'E-maili turundus lubatud', 'SMS turundus lubatud'];
foreach ($requiredCols as $name) {
    if (!isset($colIndex[$name])) {
        fwrite(STDERR, "Expected column missing from input: {$name}\n");
        exit(1);
    }
}

fputcsv($out,  OUT_HEADER, ',', '"', '');
fputcsv($skip, array_merge(['reason'], $rawHeader), ';', '"', '');

$imported    = 0;
$synthesized = 0;
$skipped     = 0;
$placeholderCounter = 0;
$seenEmails  = [];

while (($row = fgetcsv($in, 0, ';', '"', '')) !== false) {
    if (count(array_filter($row, fn($v) => $v !== '' && $v !== null)) === 0) {
        continue; // skip wholly-blank lines
    }

    $get = fn(string $col) => trim((string) ($row[$colIndex[$col]] ?? ''));

    [$first, $last] = splitName($get('Nimi'));
    $email = strtolower($get('E-mail'));
    $phone = $get('Telefon');
    $notes = $get('Üldteave');
    $synthetic = false;

    if ($email === '') {
        $placeholderCounter++;
        $email = placeholderEmail($get('Nimi'), $phone, $placeholderCounter);
        $synthetic = true;
    }

    if (isset($seenEmails[$email])) {
        $placeholderCounter++;
        $email = placeholderEmail($get('Nimi'), $phone, $placeholderCounter);
        $synthetic = true;
        if (isset($seenEmails[$email])) {
            fputcsv($skip, array_merge(['duplicate_unresolvable'], $row), ';', '"', '');
            $skipped++;
            continue;
        }
    }
    $seenEmails[$email] = true;

    if ($synthetic) {
        $notes = trim(PLACEHOLDER_NOTE . "\n" . $notes);
        $synthesized++;
    }

    fputcsv($out, [
        $first !== '' ? $first : PLACEHOLDER_TEXT,
        $last  !== '' ? $last  : PLACEHOLDER_TEXT,
        $email,
        $phone !== '' ? $phone : PLACEHOLDER_PHONE,
        $get('Aadress'),
        $get('Postiindeks'),
        $get('Linn'),
        $notes,
        $get('Püsiklient'),
        $get('Loodud kell'),
        yesNo($get('E-maili turundus lubatud')),
        yesNo($get('SMS turundus lubatud')),
    ], ',', '"', '');
    $imported++;
}

fclose($in);
fclose($out);
fclose($skip);

// Split the main file into chunks
$chunks = writeChunks($outPath, $chunkSize);

echo "Source:      {$inputPath}\n";
echo "Output:      {$outPath}\n";
echo "Skipped log: {$skipPath}\n";
echo "Imported:    {$imported}\n";
echo "  synthetic: {$synthesized}\n";
echo "Skipped:     {$skipped}\n";
echo "Chunks:      {$chunks} files of up to {$chunkSize} rows\n";

function splitName(string $full): array
{
    $full = trim($full);
    if ($full === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/', $full, 2);
    return [$parts[0], $parts[1] ?? ''];
}

function placeholderEmail(string $name, string $phone, int $idx): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if ($digits !== '') {
        return "noemail+{$digits}@" . PLACEHOLDER_HOST;
    }
    $slug = preg_replace('/[^a-z0-9]+/', '', strtolower($name));
    if ($slug === '') {
        $slug = 'unknown';
    }
    return "noemail+{$slug}{$idx}@" . PLACEHOLDER_HOST;
}

function yesNo(string $value): string
{
    return match (strtolower(trim($value))) {
        'jah'   => 'yes',
        'mitte' => 'no',
        default => '',
    };
}

function writeChunks(string $mainPath, int $size): int
{
    $in = fopen($mainPath, 'r');
    $header = fgetcsv($in, 0, ',', '"', '');
    $rows   = [];
    while (($r = fgetcsv($in, 0, ',', '"', '')) !== false) {
        $rows[] = $r;
    }
    fclose($in);

    $base = preg_replace('/\.csv$/', '', $mainPath);
    $total = count($rows);
    $chunks = (int) ceil($total / $size);
    for ($i = 0; $i < $chunks; $i++) {
        $path = sprintf('%s_part%02d.csv', $base, $i + 1);
        $fh   = fopen($path, 'w');
        fputcsv($fh, $header, ',', '"', '');
        foreach (array_slice($rows, $i * $size, $size) as $r) {
            fputcsv($fh, $r, ',', '"', '');
        }
        fclose($fh);
    }
    return $chunks;
}
