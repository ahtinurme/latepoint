<?php
/**
 * Import handwritten consent forms as NATIVE Ninja Forms submissions.
 *
 * Parses the transcribed scans (folders 1–6), matches each to a LatePoint
 * customer, uploads the original scan(s) to the media library, and creates a
 * real Ninja Forms submission (`nf_sub`) against the matching consent form
 * (build-ninja-forms.php must have created them first). The submission is
 * stamped with `_latepoint_customer_id` so the latepoint-ninja-forms bridge can
 * list it on the customer, and shows up natively under Ninja Forms → Submissions.
 *
 * Source layout: folders 1+2 and 3+6 are each one 2-page form (page 2 = page-1
 * image number + 1); folders 4 and 5 are single-page forms.
 *
 * Run ON THE SERVER (needs DB + WP + ninja-forms):
 *   php wp-content/plugins/scripts/forms/import-consent-forms.php            # dry run, reports matches
 *   php wp-content/plugins/scripts/forms/import-consent-forms.php --commit   # write native subs + purge legacy meta
 *
 * Idempotent: skips a scan whose `_yumefit_paper_sub` marker already exists.
 * ponytail: one script, dry-run by default — production writes are opt-in.
 */

if ( php_sapi_name() !== 'cli' ) {
  exit( "CLI only.\n" );
}

$COMMIT = in_array( '--commit', $argv, true );
$HERE   = __DIR__;

// Parser self-test — pure string logic, no WP. Run locally: `php import-consent-forms.php --self-test`.
if ( in_array( '--self-test', $argv, true ) ) { exit( consent_parser_self_test() ? 0 : 1 ); }

// Locate and boot WordPress.
$wp_load = null;
for ( $dir = $HERE, $i = 0; $i < 8; $i++, $dir = dirname( $dir ) ) {
  if ( file_exists( "$dir/wp-load.php" ) ) { $wp_load = "$dir/wp-load.php"; break; }
}
if ( ! $wp_load ) { exit( "wp-load.php not found.\n" ); }
require_once $wp_load;

if ( ! class_exists( 'OsCustomerModel' ) ) {
  exit( "LatePoint not loaded — is the plugin active?\n" );
}
if ( ! function_exists( 'Ninja_Forms' ) ) {
  exit( "Ninja Forms not loaded — run build-ninja-forms.php first, on the server.\n" );
}
require_once ABSPATH . 'wp-admin/includes/image.php';

// One consent form per scan grouping (folders 1+2, 3+6, 4, 5). The native subs
// are created against the form whose title matches; build-ninja-forms.php owns
// these definitions. Keyed by scan group -> form title.
const FORM_TYPES = [
  '1-2' => [ 'title' => 'Kliendi teavitamine ja nõusolek (2-lk, I)' ],
  '3-6' => [ 'title' => 'Kliendi teavitamine ja nõusolek (2-lk, II)' ],
  '4'   => [ 'title' => 'Kliendi teavitamine ja nõusolek (1-lk, I)' ],
  '5'   => [ 'title' => 'Kliendi teavitamine ja nõusolek (1-lk, II)' ],
];
const SUB_MARKER   = '_yumefit_consent_src';      // attachment meta: source basename
const PAPER_MARKER = '_yumefit_paper_sub';        // nf_sub meta: paper_<img>, idempotency key
const LP_CUSTOMER_FK = '_latepoint_customer_id';  // nf_sub meta: links sub -> LatePoint customer
const LEGACY_META  = 'ninja_form_submissions';    // old customer/order meta JSON store, now purged

// Manual overrides for scans whose transcribed name/email didn't auto-match a
// customer (transcription typos / contact under a different address). Keyed by
// the page-1 scan basename, confirmed by hand against unmatched-proposals.md.
const OVERRIDES = [
  'IMG_0498' => 227, // Jekaterina Barofova  -> Barotova
  'IMG_0533' => 231, // Jelena Kremnjova     -> Kremnjeva
  'IMG_0512' => 647, // Viktoria Romasova    -> Rozanova
  'IMG_0502' => 272, // Kaido-Mart Kangro    -> Kaivo-Mart
  'IMG_0622' => 90,  // Brigith Laureen Hanik-> Harik
  'IMG_0614' => 226, // Janne Saarnits       -> Säärits
  'IMG_0583' => 458, // Marita Mattisen      -> Mariita Mattiisen
  'IMG_0535' => 535, // Olga Aleusandrova    -> Aleksandrova
  'IMG_0598' => 52,  // Anneli Talih         -> Talik
  'IMG_0565' => 83,  // Ave Kaljuste         -> Avo Kaljuste
  'IMG_0587' => 58,  // Anne-Ly Nips         -> Anne-Ly Vips
];

/* ===========================================================================
 * Transcript parser — turns the structured-markdown scan transcript into a
 * { field_key => value } map matching the Ninja Form's granular fields.
 *
 * Pure string logic (no WP), so it is unit-tested by `--self-test`. Robust to
 * the real format drift in the scans: inserted words ("kuidas KVALITEETSEKS
 * hindad..."), typos, `- [x]` checkboxes AND inline `(circled)` answers, and the
 * consent line that carries no marker (the paper was signed -> treated as given).
 * ========================================================================= */

function nfc_norm( string $s ): string {
  $s = str_replace( [ '**', '*' ], '', $s );
  $s = mb_strtolower( $s, 'UTF-8' );
  $s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
  return trim( preg_replace( '/\s+/', ' ', $s ) );
}

/** @return array<int, string> */
function nfc_tokens( string $s ): array {
  $n = nfc_norm( $s );
  return $n === '' ? [] : explode( ' ', $n );
}

/** True if every token of $needle appears in $hay in order (gaps allowed). */
function nfc_is_subseq( array $needle, array $hay ): bool {
  if ( ! $needle ) { return false; }
  $i = 0;
  foreach ( $hay as $tok ) {
    if ( $tok === $needle[ $i ] && ++$i === count( $needle ) ) { return true; }
  }
  return false;
}

/** Match a checked option's text to one of a field's options; returns the option VALUE or null. */
function nfc_match_option( string $text, array $options ): ?string {
  $t = nfc_norm( preg_replace( '/:.*$/u', '', $text ) ); // drop "Muu: ...." tails
  if ( $t === '' ) { return null; }
  foreach ( $options as $label => $value ) {
    $o = nfc_norm( $label );
    if ( $o !== '' && ( $o === $t || strpos( $t, $o ) === 0 || strpos( $o, $t ) === 0 ) ) {
      return $value;
    }
  }
  return null;
}

/**
 * @param array<int, array{key:string,type:string,label:string,options:array<string,string>}> $spec
 *        Ordered, value-bearing fields only (no html/submit).
 * @return array{values: array<string, mixed>, anomalies: array<int, string>}
 */
function parse_consent_transcript( string $text, array $spec ): array {
  $lines = preg_split( '/\r\n|\r|\n/', $text );
  $line_tokens = array_map( 'nfc_tokens', $lines );

  // Order-tolerant anchoring: walking the scan top-to-bottom, each line is claimed by
  // the most specific (longest-label) still-unanchored field whose leading label tokens
  // are a subsequence of the line. The scans don't always follow the template field
  // order, so anchors are assigned by scan position, NOT template position.
  $needles = [];
  foreach ( $spec as $i => $f ) { $needles[ $i ] = array_slice( nfc_tokens( $f['label'] ), 0, 6 ); }

  $anchor = array_fill( 0, count( $spec ), null );
  foreach ( $line_tokens as $ln => $toks ) {
    if ( ! $toks ) { continue; }
    $best = null; $best_len = 0;
    foreach ( $spec as $i => $f ) {
      if ( $anchor[ $i ] !== null ) { continue; }
      if ( count( $needles[ $i ] ) > $best_len && nfc_is_subseq( $needles[ $i ], $toks ) ) {
        $best = $i; $best_len = count( $needles[ $i ] );
      }
    }
    if ( $best !== null ) { $anchor[ $best ] = $ln; }
  }

  // Block boundaries follow anchor POSITION: a field's block runs to the next anchor down the page.
  $sorted = array_filter( $anchor, fn( $v ) => $v !== null );
  asort( $sorted );
  $anchor_lines = array_values( $sorted );

  $values = [];
  $anomalies = [];
  foreach ( $spec as $i => $f ) {
    if ( $anchor[ $i ] === null ) {
      $anomalies[] = "no anchor for `{$f['key']}`";
      continue;
    }
    $start = $anchor[ $i ];
    $end   = count( $lines );
    foreach ( $anchor_lines as $al ) {
      if ( $al > $start ) { $end = $al; break; }
    }
    $block = array_slice( $lines, $start, max( 1, $end - $start ) );

    if ( $f['type'] === 'checkbox' ) { // single consent — paper was signed
      $values[ $f['key'] ] = '1';
      continue;
    }

    if ( in_array( $f['type'], [ 'listradio', 'listcheckbox' ], true ) ) {
      $picked = [];
      $explicit = false; // saw at least one real `[x]` box
      foreach ( $block as $bl ) {
        if ( ! preg_match( '/\[\s*x\s*\]\s*(.*)$/iu', $bl, $m ) ) { continue; }
        $c = trim( $m[1] );
        if ( $c === '' || mb_strtolower( $c ) === 'x' ) { continue; } // empty / stray "x"
        $explicit = true;
        $v = nfc_match_option( $c, $f['options'] );
        if ( $v !== null ) { $picked[] = $v; }
        else { $anomalies[] = "`{$f['key']}` unmatched option: " . $c; }
      }
      if ( ! $picked && ! $explicit ) { // inline circled answer ("Hea / (Keskmine) / Halb") — speculative, silent on miss
        if ( preg_match_all( '/\(([^)]+)\)/u', implode( ' ', $block ), $m ) ) {
          foreach ( $m[1] as $c ) {
            $v = nfc_match_option( trim( $c ), $f['options'] );
            if ( $v !== null ) { $picked[] = $v; }
          }
        }
      }
      if ( $picked ) {
        $values[ $f['key'] ] = $f['type'] === 'listradio' ? $picked[0] : array_values( array_unique( $picked ) );
      }
      continue;
    }

    // text / textarea / date / email / phone — value after the label separator, stopping before
    // anything that isn't this field's answer: option lines, the "---- lk 2 ----" page break, and
    // the static contraindications boilerplate that follows some questions.
    $stop = function ( string $l ): bool {
      return (bool) preg_match( '/\[\s*[x ]?\s*\]/iu', $l )       // option line
        || (bool) preg_match( '/-{2,}\s*lk/iu', $l )              // page separator
        || (bool) preg_match( '/^\s*\d\.\d/u', $l )               // "1.1 ..." contraindication numbering
        || mb_stripos( $l, 'vastunäidust' ) !== false
        || mb_stripos( $l, 'südamestimulaator' ) !== false;
    };
    $clean = function ( string $p ): string {
      $p = str_replace( [ '**', '*', '[signature]' ], '', $p );
      return trim( preg_replace( '/\.{2,}/', '', $p ) );
    };

    $inline = $clean( preg_replace( '/^.*?[:?]/u', '', str_replace( '**', '', $block[0] ), 1 ) );
    $collected = ( $inline !== '' ) ? [ $inline ] : [];

    // Single-line fields take just one answer; textarea collects until a blank line / stop marker.
    if ( $f['type'] === 'textarea' || ! $collected ) {
      for ( $k = 1; $k < count( $block ); $k++ ) {
        if ( $stop( $block[ $k ] ) ) { break; }
        $p = $clean( $block[ $k ] );
        if ( $p === '' ) { if ( $collected ) { break; } continue; }
        $collected[] = $p;
        if ( $f['type'] !== 'textarea' ) { break; }
      }
    }

    $val = trim( implode( "\n", $collected ) );
    if ( $val !== '' ) { $values[ $f['key'] ] = $val; }
  }

  return [ 'values' => $values, 'anomalies' => $anomalies ];
}

/** Self-test the parser against the real format drift seen in the scans. Returns true if all pass. */
function consent_parser_self_test(): bool {
  $text = implode( "\n", [
    '**Kliendi ees- ja perekonnanimi:** Jekaterina Barofova',
    '**Sünniaeg:** 22.04.1990',
    '**Vastunäidustused, mille olemasolul ei tohi teenust kasutada:**',   // must NOT bleed into synniaeg
    'südamestimulaatori kasutamine ja südame arütmia, tromboos, epilepsia',
    '**Kas oled varem EMS treeningul osalenud?**',
    '- [x] Ei',
    '- [ ] Jah',
    'Millal viimati? ..........',
    '**Kas Sul on olnud vigastusi, püsivat valu või muid kaebusi nendes piirkondades?**',
    '- [ ] Kael',
    '- [ ] Õlad',
    'Täpsustus: Ei olnud',
    'Kuidas kvaliteetseks hindad enda toitumisharjumusi?',   // inserted word "kvaliteetseks"
    'Hea / (Keskmine) / Halb',                                // inline circled answer
    'Mis on Sinu eesmärk treeninguga?',
    '- [x] Kaalulangetus',
    '- [ ] Lihastoonuse tõstmine',
    '- [x] Üldine füüsilise aktiivsuse tõstmine',
    '- [ ] Muu',
    '---- lk 2 ----',                                          // page separator, must NOT be captured
    'Kas on veel midagi, millest sooviksid meid teavitada?',
    'EI',
    '..........',
    'Kinnitan, et endale parimate teadmiste kohaselt ei ole mul loetletud vastunäidustusi',
    'Kuupäev: 15.02.26',
    'Allkiri: [signature]',
    'Treeneri nimi: Alexandra',
  ] );

  $spec = [
    [ 'key' => 'eesnimi',          'type' => 'textbox',      'label' => 'Kliendi ees- ja perekonnanimi', 'options' => [] ],
    [ 'key' => 'synniaeg',         'type' => 'date',         'label' => 'Sünniaeg', 'options' => [] ],
    [ 'key' => 'varem_ems',        'type' => 'listradio',    'label' => 'Kas oled varem EMS treeningul osalenud?', 'options' => [ 'Ei' => 'ei', 'Jah' => 'jah' ] ],
    [ 'key' => 'varem_ems_millal', 'type' => 'textbox',      'label' => 'Millal viimati?', 'options' => [] ],
    [ 'key' => 'vigastused',       'type' => 'listcheckbox', 'label' => 'Kas Sul on olnud vigastusi, püsivat valu või muid kaebusi nendes piirkondades?', 'options' => [ 'Kael' => 'kael', 'Õlad' => 'olad' ] ],
    [ 'key' => 'vigastused_tapsustus', 'type' => 'textbox',  'label' => 'Täpsustus', 'options' => [] ],
    [ 'key' => 'toitumine',        'type' => 'listradio',    'label' => 'Kuidas hindad enda toitumisharjumusi?', 'options' => [ 'Hea' => 'hea', 'Keskmine' => 'keskmine', 'Halb' => 'halb' ] ],
    [ 'key' => 'eesmark',          'type' => 'listcheckbox', 'label' => 'Mis on Sinu eesmärk treeninguga?', 'options' => [ 'Kaalulangetus' => 'kaalulangetus', 'Lihastoonuse tõstmine' => 'lihas', 'Üldine füüsilise aktiivsuse tõstmine' => 'uldine', 'Muu' => 'muu' ] ],
    [ 'key' => 'muu_teave',        'type' => 'textarea',     'label' => 'Kas on veel midagi, millest sooviksid meid teavitada?', 'options' => [] ],
    [ 'key' => 'kinnitus',         'type' => 'checkbox',     'label' => 'Kinnitan, et endale parimate teadmiste kohaselt', 'options' => [] ],
    [ 'key' => 'kuupaev',          'type' => 'date',         'label' => 'Kuupäev', 'options' => [] ],
    [ 'key' => 'allkiri',          'type' => 'textbox',      'label' => 'Allkiri', 'options' => [] ],
    [ 'key' => 'treener',          'type' => 'textbox',      'label' => 'Treeneri nimi', 'options' => [] ],
  ];

  $v = parse_consent_transcript( $text, $spec )['values'];

  $checks = [
    'eesnimi text'                 => ( $v['eesnimi'] ?? null ) === 'Jekaterina Barofova',
    'synniaeg date'                => ( $v['synniaeg'] ?? null ) === '22.04.1990',
    'radio [x]'                    => ( $v['varem_ems'] ?? null ) === 'ei',
    'empty dotted field skipped'   => ! isset( $v['varem_ems_millal'] ),
    'unchecked checkbox skipped'   => ! isset( $v['vigastused'] ),
    'free text after label'        => ( $v['vigastused_tapsustus'] ?? null ) === 'Ei olnud',
    'inline circled answer'        => ( $v['toitumine'] ?? null ) === 'keskmine',
    'multi checkbox'               => ( $v['eesmark'] ?? null ) === [ 'kaalulangetus', 'uldine' ],
    'textarea answer, no separator'=> ( $v['muu_teave'] ?? null ) === 'EI',
    'implicit consent checked'     => ( $v['kinnitus'] ?? null ) === '1',
    'date with year suffix'        => ( $v['kuupaev'] ?? null ) === '15.02.26',
    'signature-only skipped'       => ! isset( $v['allkiri'] ),
    'trainer name'                 => ( $v['treener'] ?? null ) === 'Alexandra',
  ];

  $ok = true;
  foreach ( $checks as $name => $pass ) {
    echo ( $pass ? "  PASS  " : "  FAIL  " ) . $name . "\n";
    if ( ! $pass ) { $ok = false; }
  }
  echo $ok ? "\nAll parser self-tests passed.\n" : "\nSELF-TEST FAILED.\n";
  return $ok;
}

/* ---------- name normalisation for matching ---------- */
function normalize_name( string $s ): string {
  $s = mb_strtolower( trim( $s ), 'UTF-8' );
  $map = [ 'ä'=>'a','õ'=>'o','ö'=>'o','ü'=>'u','š'=>'s','ž'=>'z','é'=>'e','è'=>'e','á'=>'a','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ç'=>'c' ];
  $s = strtr( $s, $map );
  $s = preg_replace( '/[^a-z0-9 ]+/', ' ', $s );
  $tokens = array_values( array_filter( explode( ' ', $s ) ) );
  sort( $tokens );
  return implode( ' ', $tokens );
}

/* ---------- parse the transcribed scans ---------- */

/**
 * Build one entry per customer submission from the transcribe-merged JSON files.
 * Folders 1+2 and 3+6 are merged into 2-page forms; 4 and 5 are single-page.
 * ponytail: full transcript kept as one text field — lossless, no per-checkbox parsing.
 *
 * @return array<int, array<string, mixed>>
 */
function build_entries( string $base ): array {
  $sources = [
    '1'  => '1/transcribe-merged-2c5a6cfe1e7e.json',
    '2'  => '2/transcribe-merged-8f6cc265d294.json',
    '3'  => '3/transcribe-merged-c278192c58e9.json',
    '4a' => '4/transcribe-merged-43c6f8278b33.json',
    '4b' => '4/transcribe-merged-e5734ee9c6ea.json',
    '5'  => '5/transcribe-merged-e05af8758329.json',
    '6'  => '6/transcribe-merged-c0b8fc05189e.json',
  ];
  $img_dir = [ '1'=>'1', '2'=>'2', '3'=>'3', '4a'=>'4', '4b'=>'4', '5'=>'5', '6'=>'6' ];
  $group   = [ '1'=>'1-2', '3'=>'3-6', '4a'=>'4', '4b'=>'4', '5'=>'5' ];

  $load = function( string $folder ) use ( $base, $sources ): array {
    $data = json_decode( file_get_contents( "$base/{$sources[$folder]}" ), true );
    return is_array( $data ) ? $data : [];
  };
  $img_num     = fn( string $f ) => preg_match( '/(\d+)/', $f, $m ) ? (int) $m[1] : 0;
  $transcript  = fn( array $r ) => $r['results'][0]['transcript'] ?? '';
  // records of a folder keyed by image number
  $by_num = function( string $folder ) use ( $load, $img_num, $transcript ): array {
    $out = [];
    foreach ( $load( $folder ) as $r ) {
      $out[ $img_num( $r['file_name'] ) ] = [ $r['file_name'], $transcript( $r ) ];
    }
    return $out;
  };

  $field = function( string $pattern, string $text ): string {
    if ( ! preg_match( $pattern, $text, $m ) ) { return ''; }
    $v = str_replace( '*', '', $m[1] );
    $v = trim( trim( $v ), '.' );
    return trim( preg_replace( '/\s+/', ' ', $v ) );
  };
  $NAME  = '/(?:Kliendi ees- ja perekonnanimi|Ees- ja perekonnanimi)[:*\s]*([^\n]+)/ui';
  $DOB   = '/S[üu]nniaeg[:*\s]*([^\n]+)/ui';
  $EMAIL = '/E-?mail[:*\s]*([^\n]+)/ui';
  $PHONE = '/Telefon[:*\s]*([^\n]+)/ui';

  $make = function( string $folder, string $file_name, string $text, ?array $page2 )
      use ( $img_dir, $group, $field, $NAME, $DOB, $EMAIL, $PHONE ): array {
    $images = [ "{$img_dir[$folder]}/$file_name" ];
    $full   = $text;
    if ( $page2 ) {
      [ $p2_name, $p2_text ] = $page2;
      $images[] = ( $folder === '1' ? '2' : '6' ) . "/$p2_name";
      $full     = $text . "\n\n---- lk 2 ----\n\n" . $p2_text;
    }
    $type = FORM_TYPES[ $group[ $folder ] ];
    return [
      'source'     => $folder,
      'form_title' => $type['title'],
      'name'       => $field( $NAME, $text ),
      'dob'        => $field( $DOB, $text ),
      'email'      => $field( $EMAIL, $text ),
      'phone'      => $field( $PHONE, $text ),
      'images'     => $images,
      'transcript' => $full,
    ];
  };

  $entries = [];

  // Form A: folder 1 (page1) + folder 2 (page2 = num+1)
  $p2 = $by_num( '2' );
  foreach ( $load( '1' ) as $r ) {
    $n = $img_num( $r['file_name'] );
    $entries[] = $make( '1', $r['file_name'], $transcript( $r ), $p2[ $n + 1 ] ?? null );
  }
  // Form B: folder 3 (page1) + folder 6 (page2 = num+1)
  $p2 = $by_num( '6' );
  foreach ( $load( '3' ) as $r ) {
    $n = $img_num( $r['file_name'] );
    $entries[] = $make( '3', $r['file_name'], $transcript( $r ), $p2[ $n + 1 ] ?? null );
  }
  // Form C: folder 4 (single page, two transcript batches)
  foreach ( [ '4a', '4b' ] as $folder ) {
    foreach ( $load( $folder ) as $r ) {
      $entries[] = $make( $folder, $r['file_name'], $transcript( $r ), null );
    }
  }
  // Form D: folder 5 (single page)
  foreach ( $load( '5' ) as $r ) {
    $entries[] = $make( '5', $r['file_name'], $transcript( $r ), null );
  }

  return $entries;
}

/* ---------- load customers + indices ---------- */
$customers = ( new OsCustomerModel() )->get_results_as_models();
$by_email = [];
$by_name  = [];
$by_id    = [];
foreach ( $customers as $c ) {
  $by_id[ (int) $c->id ] = $c;
  if ( ! empty( $c->email ) ) {
    $by_email[ mb_strtolower( trim( $c->email ), 'UTF-8' ) ] = $c;
  }
  $by_name[ normalize_name( $c->full_name ) ][] = $c;
}
echo "Loaded " . count( $customers ) . " LatePoint customers.\n";

/* ---------- resolve the live Ninja Forms: title -> id, and id -> (key -> field_id) ---------- */
$form_id_by_title = [];
$field_map        = []; // form_id => [ field_key => field_id ]
$form_spec        = []; // form_id => ordered value-bearing field descriptors (for the parser)
foreach ( Ninja_Forms()->form()->get_forms() as $f ) {
  $title = $f->get_setting( 'title' );
  if ( ! in_array( $title, array_column( FORM_TYPES, 'title' ), true ) ) { continue; }
  $fid = (int) $f->get_id();
  $form_id_by_title[ $title ] = $fid;

  $fields = Ninja_Forms()->form( $fid )->get_fields();
  usort( $fields, fn( $a, $b ) => (int) $a->get_setting( 'order' ) <=> (int) $b->get_setting( 'order' ) );
  foreach ( $fields as $field ) {
    $key  = $field->get_setting( 'key' );
    $type = $field->get_setting( 'type' );
    $field_map[ $fid ][ $key ] = (int) $field->get_id();
    if ( in_array( $type, [ 'html', 'submit', 'hr' ], true ) || $key === 'originaaldokument' ) { continue; }
    $options = [];
    foreach ( (array) $field->get_setting( 'options' ) as $o ) {
      if ( isset( $o['label'], $o['value'] ) ) { $options[ $o['label'] ] = $o['value']; }
    }
    $form_spec[ $fid ][] = [ 'key' => $key, 'type' => $type, 'label' => (string) $field->get_setting( 'label' ), 'options' => $options ];
  }
}
foreach ( FORM_TYPES as $t ) {
  if ( empty( $form_id_by_title[ $t['title'] ] ) ) {
    exit( "Missing Ninja Form: \"{$t['title']}\". Run build-ninja-forms.php --commit first.\n" );
  }
}
echo "Resolved " . count( $form_id_by_title ) . " consent forms.\n";

/* ---------- parse the transcribed scans into entries ---------- */
$entries = build_entries( $HERE );
echo "Parsed " . count( $entries ) . " form entries.\n";
echo $COMMIT ? "MODE: COMMIT (writing)\n\n" : "MODE: DRY RUN (no writes; pass --commit to apply)\n\n";

/* ---------- match + import ---------- */
$matched = $ambiguous = $unmatched = $imported = $skipped = 0;
$coverage_filled = $coverage_total = $anomaly_total = 0;
$by_type = [];

function find_existing_attachment( string $basename ): ?int {
  $q = get_posts( [
    'post_type'   => 'attachment',
    'post_status' => 'inherit',
    'numberposts' => 1,
    'fields'      => 'ids',
    'meta_key'    => SUB_MARKER,
    'meta_value'  => $basename,
  ] );
  return $q ? (int) $q[0] : null;
}

function sideload_image( string $path ): ?array {
  $basename = basename( $path );
  if ( $id = find_existing_attachment( $basename ) ) {
    return [ 'id' => $id, 'url' => wp_get_attachment_url( $id ) ];
  }
  $up = wp_upload_bits( $basename, null, file_get_contents( $path ) );
  if ( ! empty( $up['error'] ) ) { return null; }
  $attach_id = wp_insert_attachment( [
    'post_mime_type' => 'image/jpeg',
    'post_title'     => preg_replace( '/\.[^.]+$/', '', $basename ),
    'post_status'    => 'inherit',
  ], $up['file'] );
  if ( ! $attach_id ) { return null; }
  wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $up['file'] ) );
  update_post_meta( $attach_id, SUB_MARKER, $basename );
  return [ 'id' => $attach_id, 'url' => $up['url'] ];
}

function already_imported( string $sub_id ): bool {
  $existing = get_posts( [
    'post_type'   => 'nf_sub',
    'post_status' => 'any',
    'numberposts' => 1,
    'fields'      => 'ids',
    'meta_key'    => PAPER_MARKER,
    'meta_value'  => $sub_id,
  ] );
  return ! empty( $existing );
}

foreach ( $entries as $e ) {
  $name   = $e['name'];
  $sub_id = 'paper_' . preg_replace( '/\.[^.]+$/', '', basename( $e['images'][0] ) );

  // match: manual override first, then email, then normalized name
  $customer = null; $how = '';
  $img_key = preg_replace( '/\.[^.]+$/', '', basename( $e['images'][0] ) );
  $email = $e['email'] ? mb_strtolower( trim( $e['email'] ), 'UTF-8' ) : '';
  if ( isset( OVERRIDES[ $img_key ] ) && isset( $by_id[ OVERRIDES[ $img_key ] ] ) ) {
    $customer = $by_id[ OVERRIDES[ $img_key ] ]; $how = 'override #' . OVERRIDES[ $img_key ];
  } elseif ( $email && isset( $by_email[ $email ] ) ) {
    $customer = $by_email[ $email ]; $how = "email $email";
  } else {
    $cands = $by_name[ normalize_name( $name ) ] ?? [];
    if ( count( $cands ) === 1 ) { $customer = $cands[0]; $how = 'name'; }
    elseif ( count( $cands ) > 1 ) {
      $ambiguous++;
      echo sprintf( "  AMBIGUOUS  %-28s -> %d customers (%s)\n", $name, count( $cands ),
        implode( ', ', array_map( fn( $c ) => "#{$c->id}", $cands ) ) );
      continue;
    }
  }

  if ( ! $customer ) {
    $unmatched++;
    echo sprintf( "  NO MATCH   %-28s  %s\n", $name, $e['email'] ?: '' );
    continue;
  }

  $matched++;
  if ( already_imported( $sub_id ) ) {
    $skipped++;
    echo sprintf( "  skip(dup)  %-28s -> #%d %s\n", $name, $customer->id, $customer->full_name );
    continue;
  }

  $by_type[ $e['form_title'] ] = ( $by_type[ $e['form_title'] ] ?? 0 ) + 1;

  // parse the transcript into granular field values (both modes, so dry-run reports coverage)
  $form_id = $form_id_by_title[ $e['form_title'] ];
  $parsed  = parse_consent_transcript( $e['transcript'], $form_spec[ $form_id ] );
  $filled  = count( $parsed['values'] );
  $total   = count( $form_spec[ $form_id ] );
  $coverage_filled += $filled;
  $coverage_total  += $total;
  $anomaly_total   += count( $parsed['anomalies'] );

  echo sprintf( "  MATCH      %-26s -> #%d %s  {%s}  %d/%d fields%s\n",
    $name, $customer->id, $customer->full_name, $e['form_title'], $filled, $total,
    $parsed['anomalies'] ? "\n               ⚠ " . implode( '; ', $parsed['anomalies'] ) : '' );

  if ( ! $COMMIT ) { continue; }

  // upload scan(s) and attach as the originaaldokument field
  $urls = [];
  foreach ( $e['images'] as $rel ) {
    $res = sideload_image( "$HERE/$rel" );
    if ( $res ) { $urls[] = $res['url']; }
  }
  if ( $urls ) { $parsed['values']['originaaldokument'] = implode( "\n", $urls ); }

  $sub = new NF_Database_Models_Submission( '', $form_id );
  foreach ( $parsed['values'] as $key => $value ) {
    if ( empty( $field_map[ $form_id ][ $key ] ) ) { continue; }
    $sub->update_field_value( $field_map[ $form_id ][ $key ], $value );
  }
  $sub->update_extra_value( LP_CUSTOMER_FK, (int) $customer->id );
  $sub->update_extra_value( PAPER_MARKER, $sub_id );
  $sub->save();
  $imported++;
}

/* ---------- full replace: purge the legacy meta JSON store ---------- */
if ( $COMMIT ) {
  global $wpdb;
  $purged = 0;
  foreach ( [ 'latepoint_customer_meta', 'latepoint_order_meta' ] as $table ) {
    $purged += (int) $wpdb->query( $wpdb->prepare(
      "DELETE FROM {$wpdb->prefix}{$table} WHERE meta_key = %s", LEGACY_META
    ) );
  }
  echo "\nPurged $purged legacy `" . LEGACY_META . "` meta rows.\n";
}

echo "\n==== SUMMARY ====\n";
echo "matched:   $matched\n";
foreach ( $by_type as $title => $n ) { echo "  - $title: $n\n"; }
echo "  imported:$imported  skipped(dup): $skipped\n";
echo "ambiguous: $ambiguous  (resolve by hand)\n";
echo "unmatched: $unmatched\n";
if ( $coverage_total ) {
  echo sprintf( "coverage:  %d/%d fields filled (%.0f%%) across matched forms; %d parse anomalies (⚠ above)\n",
    $coverage_filled, $coverage_total, 100 * $coverage_filled / $coverage_total, $anomaly_total );
}
if ( ! $COMMIT ) { echo "\nDry run only. Re-run with --commit to write.\n"; }
