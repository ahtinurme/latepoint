#!/usr/bin/env php
<?php
/**
 * Costume size ("Kostüümi suurus") customer custom field: create + backfill.
 *
 *  1. Resolves the customer custom field whose label matches "kostüüm" (or use
 *     --field-id=). If none exists, CREATES it: select XXS–XXL incl. combo
 *     sizes (XS/S, S/M, M/L, L/XL), visibility
 *     admin/agent (the agent assigns the size; the customer only sees it in the
 *     cabinet via latepoint-yumefit-rules), shown as a column in the admin
 *     Customers table. Stores its id in option yumefit_costume_field_id.
 *  2. Backfills from free text: scans each customer's notes + admin_notes for
 *     sizes in the forms seen in the real data — "M suurus", "Suurus: XS",
 *     "S kostüüm", bare "XL", combos "XS/S" / "Suurus XS, S" (normalized to
 *     "XS/S"), XXS–XXL. Booking-history lines are ignored; when a line mentions
 *     suurus/kost/size, only those lines are trusted (skips noise like names
 *     with initials). Exactly one distinct value found -> written; none or
 *     several -> listed for manual entry. Never overwrites an existing size.
 *
 *  3. Same for the optional "Rinnaplaadid" field (Rinnaplaatidega/Rinnaplaatideta,
 *     may stay empty): resolves/creates it (id -> option
 *     yumefit_costume_plates_field_id) and backfills from note lines mentioning
 *     rinnaplaa* — the -ta suffix or a negation word (mitte/ilma/ei) on the line
 *     means WITHOUT; conflicting lines are listed for manual entry.
 *
 * Idempotent: re-running skips customers with a value set. After this, both
 * fields are edited on the customer form in LatePoint admin; the size drives
 * the Kostüümid page.
 *
 * Usage: php -d memory_limit=1024M setup_costume_field.php [--field-id=...] [--dry-run]
 *        php setup_costume_field.php --test   (no WP needed: runs the parser
 *        over the local Timma export scripts/data/clients.json and prints the
 *        outcome per client, for eyeballing before touching the DB)
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['field-id:', 'dry-run', 'test']);
$fieldIdArg = isset($opts['field-id']) ? trim((string) $opts['field-id']) : '';
$dryRun = isset($opts['dry-run']);

// Dropdown options: canonical sizes + the in-between combos the studio actually assigns.
const SIZE_OPTIONS = ['XXS', 'XS', 'XS/S', 'S', 'S/M', 'M', 'M/L', 'L', 'L/XL', 'XL', 'XXL'];
const SIZE_TOKEN = '(XXS|XS|S|M|L|XL|XXL)';

/**
 * Distinct normalized size values found in free text ("M", "XS/S", ...).
 * Tokens on lines mentioning suurus/kost/size win over tokens elsewhere.
 *
 * @return array<int, string>
 */
function yumefit_parse_sizes_from_text(string $text): array {
    $all = [];
    $keyword = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        if (preg_match('/^\s*\d{1,2}\.\d{1,2}\.\d{4}:/', $line) || stripos($line, 'Broneerimisteave') !== false) {
            continue; // imported Timma booking-history lines
        }
        $tokens = [];
        // (*UCP): unicode word boundaries, else the m in "kostüüm" matches \bM\b
        $combo_re = '/(*UCP)\b' . SIZE_TOKEN . '\s*[\/,-]\s*' . SIZE_TOKEN . '\b/iu';
        if (preg_match_all($combo_re, $line, $m, PREG_SET_ORDER)) {
            $order = array_flip(['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL']);
            foreach ($m as $mm) {
                [$a, $b] = [strtoupper($mm[1]), strtoupper($mm[2])];
                if ($order[$a] > $order[$b]) {
                    [$a, $b] = [$b, $a]; // "S/xs" and "Xs-S" both mean XS/S
                }
                $tokens[] = $a === $b ? $a : "{$a}/{$b}";
            }
            $line_rest = preg_replace($combo_re, ' ', $line);
        } else {
            $line_rest = $line;
        }
        if (preg_match_all('/(*UCP)\b' . SIZE_TOKEN . '\b/iu', $line_rest, $m)) {
            $tokens = array_merge($tokens, array_map('strtoupper', $m[1]));
        }
        if (!$tokens) {
            continue;
        }
        $all = array_merge($all, $tokens);
        if (preg_match('/suurus|kost|size/i', $line)) {
            $keyword = array_merge($keyword, $tokens);
        }
    }
    return array_values(array_unique($keyword ?: $all));
}

const PLATES_OPTIONS = ['Rinnaplaatidega', 'Rinnaplaatideta'];

/** @return string 'Rinnaplaatidega', 'Rinnaplaatideta', '' (no info) or 'CONFLICT' */
function yumefit_parse_plates_from_text(string $text): string {
    $verdicts = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        if (preg_match('/^\s*\d{1,2}\.\d{1,2}\.\d{4}:/', $line) || stripos($line, 'Broneerimisteave') !== false) {
            continue;
        }
        if (mb_stripos($line, 'rinnaplaa') === false) {
            continue;
        }
        // "-ta" suffix or a negation word on the line means WITHOUT, covering
        // both "S, rinnaplaatideta!" and "Rinnaplaatidega kostüümi mitte panna"
        $without = preg_match('/rinnaplaatideta/iu', $line) || preg_match('/(*UCP)\b(mitte|ilma|ei)\b/iu', $line);
        $verdicts[$without ? 'Rinnaplaatideta' : 'Rinnaplaatidega'] = true;
    }
    if (count($verdicts) > 1) {
        return 'CONFLICT';
    }
    return $verdicts ? array_key_first($verdicts) : '';
}

// --- self-check against the local Timma export, runs without WordPress ---
if (isset($opts['test'])) {
    $clients = json_decode((string) file_get_contents(__DIR__ . '/data/clients.json'), true);
    if (!is_array($clients)) { fwrite(STDERR, "data/clients.json not readable\n"); exit(1); }
    $one = 0; $none = 0; $multi = 0; $platesFound = 0;
    foreach ($clients as $c) {
        $found = yumefit_parse_sizes_from_text((string) ($c['info'] ?? ''));
        $plates = yumefit_parse_plates_from_text((string) ($c['info'] ?? ''));
        if ($plates !== '') { $platesFound++; }
        $platesNote = $plates !== '' ? " | {$plates}" : '';
        if (!$found) {
            $none++;
            if ($plates !== '') { printf("        %-25s (no size)%s\n", $c['name'] ?? '?', $platesNote); }
            continue;
        }
        if (count($found) === 1 && in_array($found[0], SIZE_OPTIONS, true)) {
            $one++;
            printf("OK      %-25s %s%s\n", $c['name'] ?? '?', $found[0], $platesNote);
        } else {
            $multi++;
            printf("MANUAL  %-25s %s%s\n", $c['name'] ?? '?', implode(', ', $found), $platesNote);
        }
    }
    printf("\n%d parsed, %d manual, %d without size, %d with plates info (of %d clients)\n", $one, $multi, $none, $platesFound, count($clients));
    exit(0);
}

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsCustomFieldsHelper') || !class_exists('OsMetaHelper')) {
    fwrite(STDERR, "LatePoint (Pro custom fields) not loaded\n");
    exit(1);
}
global $wpdb; $P = $wpdb->prefix;

echo "=========== Costume size custom field setup ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "========================================================\n";

// --- 1. resolve or create the custom field ---
$json = OsSettingsHelper::get_settings_value('custom_fields_for_customer', false);
$fields = $json ? json_decode($json, true) : [];
$fields = is_array($fields) ? $fields : [];

$fieldId = '';
if ($fieldIdArg !== '') {
    if (!isset($fields[$fieldIdArg])) {
        fwrite(STDERR, "--field-id={$fieldIdArg} not found among customer custom fields.\n");
        exit(1);
    }
    $fieldId = $fieldIdArg;
} else {
    foreach ($fields as $id => $f) {
        if (mb_stripos((string) ($f['label'] ?? ''), 'kost') !== false) {
            $fieldId = (string) $id;
            break;
        }
    }
}

if ($fieldId === '') {
    $fieldId = OsCustomFieldsHelper::generate_custom_field_id();
    $field = [
        'id'                      => $fieldId,
        'label'                   => 'Kostüümi suurus',
        'placeholder'             => 'Kostüümi suurus',
        'type'                    => 'select',
        'options'                 => implode("\n", SIZE_OPTIONS),
        'value'                   => '',
        'width'                   => 'os-col-6',
        'visibility'              => 'admin_agent',
        'required'                => 'off',
        'hide_on_summary'         => 'on',
        'show_in_customers_table' => 'on',
    ];
    echo "No existing field matched 'kost*' — creating: id={$fieldId} label={$field['label']}\n";
    if (!$dryRun) {
        OsCustomFieldsHelper::save($field, 'customer');
    }
} else {
    $field = $fields[$fieldId];
    echo "Resolved field: id={$fieldId}  type=" . ($field['type'] ?? '?') . "  label=" . ($field['label'] ?? '') . "\n";
    if (($field['type'] ?? '') !== 'select') {
        fwrite(STDERR, "WARNING: field is '" . ($field['type'] ?? '?') . "', not 'select'.\n");
    }
    if (($field['visibility'] ?? '') !== 'admin_agent') {
        fwrite(STDERR, "WARNING: field visibility is '" . ($field['visibility'] ?? '?') . "', not 'admin_agent' — customers may be able to edit it.\n");
    }
}

if (!$dryRun) {
    update_option('yumefit_costume_field_id', $fieldId, false);
    echo "Stored option yumefit_costume_field_id={$fieldId}\n";
}

// --- 1b. resolve or create the chest plates field ---
$platesId = '';
foreach ($fields as $id => $f) {
    if (mb_stripos((string) ($f['label'] ?? ''), 'rinnaplaa') !== false) {
        $platesId = (string) $id;
        echo "Resolved plates field: id={$platesId}  label=" . ($f['label'] ?? '') . "\n";
        break;
    }
}
if ($platesId === '') {
    $platesId = OsCustomFieldsHelper::generate_custom_field_id();
    echo "No existing field matched 'rinnaplaa*' — creating: id={$platesId} label=Rinnaplaadid\n";
    if (!$dryRun) {
        OsCustomFieldsHelper::save([
            'id'                      => $platesId,
            'label'                   => 'Rinnaplaadid',
            'placeholder'             => 'Rinnaplaadid',
            'type'                    => 'select',
            'options'                 => implode("\n", PLATES_OPTIONS),
            'value'                   => '',
            'width'                   => 'os-col-6',
            'visibility'              => 'admin_agent',
            'required'                => 'off',
            'hide_on_summary'         => 'on',
            'show_in_customers_table' => 'off',
        ], 'customer');
    }
}
if (!$dryRun) {
    update_option('yumefit_costume_plates_field_id', $platesId, false);
    echo "Stored option yumefit_costume_plates_field_id={$platesId}\n";
}

// --- 2. backfill from notes / admin_notes ---
$metaIds = function (string $key) use ($wpdb, $P): array {
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT object_id FROM {$P}latepoint_customer_meta WHERE meta_key = %s AND meta_value <> ''",
        $key
    ));
    return array_fill_keys(array_map('intval', $ids), true);
};
$existing = $metaIds($fieldId);
$existingPlates = $metaIds($platesId);

$customers = $wpdb->get_results(
    "SELECT id, first_name, last_name, notes, admin_notes FROM {$P}latepoint_customers
     WHERE COALESCE(notes,'') <> '' OR COALESCE(admin_notes,'') <> ''"
);
echo "Customers with notes: " . count($customers) . " (already sized: " . count($existing) . ")\n\n";

$written = 0; $skipped = 0; $ambiguous = []; $noSize = 0;
$platesWritten = 0; $platesConflicts = [];
foreach ($customers as $c) {
    $text = $c->notes . "\n" . $c->admin_notes;

    if (!isset($existingPlates[(int) $c->id])) {
        $plates = yumefit_parse_plates_from_text($text);
        if ($plates === 'CONFLICT') {
            $platesConflicts[] = sprintf('#%d %s %s', $c->id, $c->first_name, $c->last_name);
        } elseif ($plates !== '') {
            $platesWritten++;
            if (!$dryRun) {
                OsMetaHelper::save_customer_meta_by_key($platesId, $plates, (int) $c->id);
            }
        }
    }

    if (isset($existing[(int) $c->id])) {
        $skipped++;
        continue;
    }
    $found = yumefit_parse_sizes_from_text($text);
    if (!$found) {
        $noSize++;
        continue;
    }
    if (count($found) > 1 || !in_array($found[0], SIZE_OPTIONS, true)) {
        $ambiguous[] = sprintf('#%d %s %s: %s', $c->id, $c->first_name, $c->last_name, implode(', ', $found));
        continue;
    }
    $written++;
    if (!$dryRun) {
        OsMetaHelper::save_customer_meta_by_key($fieldId, reset($found), (int) $c->id);
    }
}

echo "==================== SUMMARY ====================\n";
echo "Sizes written: {$written} | already set: {$skipped} | no size in notes: {$noSize}\n";
echo "Plates written: {$platesWritten}\n";
if ($ambiguous) {
    echo "Ambiguous (several sizes in notes — set manually on the customer form):\n  " . implode("\n  ", $ambiguous) . "\n";
}
if ($platesConflicts) {
    echo "Plates conflicts (notes say both with and without — set manually):\n  " . implode("\n  ", $platesConflicts) . "\n";
}
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done. Both fields are now edited on the customer form; size drives the Kostüümid page.\n");
