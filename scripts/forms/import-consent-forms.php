<?php
/**
 * Import handwritten consent forms as LatePoint customer form-submissions.
 *
 * Parses the transcribed scans (folders 1–6), matches each to a LatePoint
 * customer, uploads the original scan(s) to the media library, and appends a
 * submission to the customer's `ninja_form_submissions` meta — the exact shape
 * the latepoint-ninja-forms addon renders in the customer dashboard and admin
 * order view. Idempotent: re-running skips already-imported entries/images.
 *
 * Source layout: folders 1+2 and 3+6 are each one 2-page form (page 2 = page-1
 * image number + 1); folders 4 and 5 are single-page forms.
 *
 * Run ON THE SERVER (needs DB + WP):
 *   php wp-content/plugins/scripts/forms/import-consent-forms.php            # dry run, reports matches
 *   php wp-content/plugins/scripts/forms/import-consent-forms.php --commit   # actually writes
 *
 * ponytail: one script, dry-run by default — production writes are opt-in.
 */

if ( php_sapi_name() !== 'cli' ) {
  exit( "CLI only.\n" );
}

$COMMIT = in_array( '--commit', $argv, true );
$HERE   = __DIR__;

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
require_once ABSPATH . 'wp-admin/includes/image.php';

// Four form types, one per scan grouping handed over (folders 1+2, 3+6, 4, 5).
// Each gets a stable form_id + distinct title so the latepoint-ninja-forms
// dashboard / admin order view list them as four separate forms.
// ponytail: templates 1-2 == 3-6 (2-page, no contact) and 4 == 5 (1-page, with
// contact). Kept as 4 per request — collapse to 2 ids/titles if you'd rather.
const FORM_TYPES = [
  '1-2' => [ 'id' => 1, 'title' => 'Kliendi teavitamine ja nõusolek (2-lk, I)' ],
  '3-6' => [ 'id' => 2, 'title' => 'Kliendi teavitamine ja nõusolek (2-lk, II)' ],
  '4'   => [ 'id' => 3, 'title' => 'Kliendi teavitamine ja nõusolek (1-lk, I)' ],
  '5'   => [ 'id' => 4, 'title' => 'Kliendi teavitamine ja nõusolek (1-lk, II)' ],
];
const SUB_MARKER   = '_yumefit_consent_src';   // attachment meta: source basename
const META_KEY     = 'ninja_form_submissions'; // OsNinjaFormsHelper::CUSTOMER_META_KEY

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
      'form_id'    => $type['id'],
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

/* ---------- parse the transcribed scans into entries ---------- */
$entries = build_entries( $HERE );
echo "Parsed " . count( $entries ) . " form entries.\n";
echo $COMMIT ? "MODE: COMMIT (writing)\n\n" : "MODE: DRY RUN (no writes; pass --commit to apply)\n\n";

/* ---------- match + import ---------- */
$matched = $ambiguous = $unmatched = $imported = $skipped = 0;
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

function already_imported( int $customer_id, string $sub_id ): bool {
  $raw = OsMetaHelper::get_customer_meta_by_key( META_KEY, $customer_id, '' );
  $subs = $raw ? json_decode( $raw, true ) : [];
  foreach ( ( is_array( $subs ) ? $subs : [] ) as $s ) {
    if ( ( $s['sub_id'] ?? '' ) === $sub_id ) { return true; }
  }
  return false;
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
  if ( already_imported( (int) $customer->id, $sub_id ) ) {
    $skipped++;
    echo sprintf( "  skip(dup)  %-28s -> #%d %s\n", $name, $customer->id, $customer->full_name );
    continue;
  }

  $by_type[ $e['form_title'] ] = ( $by_type[ $e['form_title'] ] ?? 0 ) + 1;
  echo sprintf( "  MATCH      %-28s -> #%d %s  [%s] {%s}\n", $name, $customer->id, $customer->full_name, $how, $e['form_title'] );

  if ( ! $COMMIT ) { continue; }

  // upload image(s)
  $urls = [];
  foreach ( $e['images'] as $rel ) {
    $res = sideload_image( "$HERE/$rel" );
    if ( $res ) { $urls[] = $res['url']; }
  }

  $fields = [ [ 'label' => 'Nimi', 'value' => $name ] ];
  if ( $e['dob'] )   { $fields[] = [ 'label' => 'Sünniaeg', 'value' => $e['dob'] ]; }
  if ( $e['email'] ) { $fields[] = [ 'label' => 'E-mail',   'value' => $e['email'] ]; }
  if ( $e['phone'] ) { $fields[] = [ 'label' => 'Telefon',  'value' => $e['phone'] ]; }
  $fields[] = [ 'label' => 'Ankeet', 'value' => $e['transcript'] ];
  if ( $urls ) { $fields[] = [ 'label' => 'Originaaldokument', 'value' => implode( "\n", $urls ) ]; }

  $entry = [
    'form_id'      => $e['form_id'],
    'form_title'   => $e['form_title'],
    'sub_id'       => $sub_id,
    'submitted_at' => current_time( 'mysql' ),
    'fields'       => $fields,
  ];

  $raw  = OsMetaHelper::get_customer_meta_by_key( META_KEY, $customer->id, '' );
  $subs = $raw ? json_decode( $raw, true ) : [];
  if ( ! is_array( $subs ) ) { $subs = []; }
  $subs[] = $entry;
  OsMetaHelper::save_customer_meta_by_key( META_KEY, wp_json_encode( $subs ), $customer->id );
  $imported++;
}

echo "\n==== SUMMARY ====\n";
echo "matched:   $matched\n";
foreach ( $by_type as $title => $n ) { echo "  - $title: $n\n"; }
echo "  imported:$imported  skipped(dup): $skipped\n";
echo "ambiguous: $ambiguous  (resolve by hand)\n";
echo "unmatched: $unmatched\n";
if ( ! $COMMIT ) { echo "\nDry run only. Re-run with --commit to write.\n"; }
