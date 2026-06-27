<?php
/**
 * For every consent-form scan that import-consent-forms.php could NOT match to a
 * LatePoint customer, propose the closest customer(s) by fuzzy name/email, so a
 * human can confirm the spelling fixes. Read-only. Writes a markdown report.
 *
 *   php -d memory_limit=512M propose-unmatched.php
 *
 * ponytail: one-off triage aid. Uses SHORTINIT (just $wpdb) to dodge the flaky
 * full-plugin boot; parsing mirrors the importer.
 */

define( 'SHORTINIT', true );
$HERE = __DIR__;
for ( $dir = $HERE, $i = 0; $i < 8; $i++, $dir = dirname( $dir ) ) {
  if ( file_exists( "$dir/wp-load.php" ) ) { require "$dir/wp-load.php"; break; }
}
global $wpdb;

/* ---------- same normalisation as the importer ---------- */
function normalize_name( string $s ): string {
  $s = mb_strtolower( trim( $s ), 'UTF-8' );
  $map = [ 'ä'=>'a','õ'=>'o','ö'=>'o','ü'=>'u','š'=>'s','ž'=>'z','é'=>'e','è'=>'e','á'=>'a','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ç'=>'c' ];
  $s = strtr( $s, $map );
  $s = preg_replace( '/[^a-z0-9 ]+/', ' ', $s );
  $tokens = array_values( array_filter( explode( ' ', $s ) ) );
  sort( $tokens );
  return implode( ' ', $tokens );
}

/* ---------- parse the scans (mirrors importer build_entries) ---------- */
$sources = [
  '1'=>'1/transcribe-merged-2c5a6cfe1e7e.json', '2'=>'2/transcribe-merged-8f6cc265d294.json',
  '3'=>'3/transcribe-merged-c278192c58e9.json', '4a'=>'4/transcribe-merged-43c6f8278b33.json',
  '4b'=>'4/transcribe-merged-e5734ee9c6ea.json', '5'=>'5/transcribe-merged-e05af8758329.json',
  '6'=>'6/transcribe-merged-c0b8fc05189e.json',
];
$img_dir = [ '1'=>'1','2'=>'2','3'=>'3','4a'=>'4','4b'=>'4','5'=>'5','6'=>'6' ];
$load = fn( string $f ) => ( is_array( $d = json_decode( file_get_contents( "$HERE/{$sources[$f]}" ), true ) ) ? $d : [] );
$img_num = fn( string $f ) => preg_match( '/(\d+)/', $f, $m ) ? (int) $m[1] : 0;
$transcript = fn( array $r ) => $r['results'][0]['transcript'] ?? '';
$field = function( string $pat, string $text ): string {
  if ( ! preg_match( $pat, $text, $m ) ) { return ''; }
  return trim( preg_replace( '/\s+/', ' ', trim( trim( str_replace( '*', '', $m[1] ) ), '.' ) ) );
};
$NAME  = '/(?:Kliendi ees- ja perekonnanimi|Ees- ja perekonnanimi)[:*\s]*([^\n]+)/ui';
$EMAIL = '/E-?mail[:*\s]*([^\n]+)/ui';

$entries = [];
$add = function( string $folder, array $r ) use ( &$entries, $img_dir, $img_num, $transcript, $field, $NAME, $EMAIL ) {
  $text = $transcript( $r );
  $entries[] = [
    'source' => $folder,
    'image'  => "{$img_dir[$folder]}/{$r['file_name']}",
    'name'   => $field( $NAME, $text ),
    'email'  => $field( $EMAIL, $text ),
  ];
};
foreach ( $load( '1' ) as $r ) { $add( '1', $r ); }
foreach ( $load( '3' ) as $r ) { $add( '3', $r ); }
foreach ( [ '4a','4b' ] as $f ) { foreach ( $load( $f ) as $r ) { $add( $f, $r ); } }
foreach ( $load( '5' ) as $r ) { $add( '5', $r ); }

/* ---------- load customers ---------- */
$rows = $wpdb->get_results( "SELECT id, first_name, last_name, email FROM {$wpdb->prefix}latepoint_customers" );
$by_email = []; $by_name = []; $customers = [];
foreach ( $rows as $c ) {
  $full = trim( "$c->first_name $c->last_name" );
  $customers[] = [ 'id'=>$c->id, 'full'=>$full, 'email'=>$c->email, 'norm'=>normalize_name( $full ) ];
  if ( $c->email ) { $by_email[ mb_strtolower( trim( $c->email ), 'UTF-8' ) ] = true; }
  $by_name[ normalize_name( $full ) ][] = $c->id;
}

/* ---------- find unmatched + propose ----------
 * Score every customer by the BEST (smallest) of normalized-name distance and,
 * when the form has an email, email distance. Surfaces both spelling typos and
 * mistranscribed emails. Returns [customerIndex => score] for the top N.
 */
function rank( string $form_name, string $form_email, array $customers, int $top = 3 ): array {
  $needle_name  = normalize_name( $form_name );
  $needle_email = mb_strtolower( trim( $form_email ), 'UTF-8' );
  $scored = [];
  foreach ( $customers as $i => $c ) {
    $d = $needle_name !== '' && $c['norm'] !== '' ? levenshtein( $needle_name, $c['norm'] ) : 99;
    if ( $needle_email !== '' && $needle_email !== '-' && $c['email'] !== '' ) {
      $d = min( $d, levenshtein( $needle_email, mb_strtolower( $c['email'], 'UTF-8' ) ) );
    }
    $scored[ $i ] = $d;
  }
  asort( $scored );
  return array_slice( $scored, 0, $top, true );
}

$lines = [ '# Unmatched consent forms — proposed customers', '',
  'Form name/email is from the scan (may contain transcription typos). Proposal is the closest LatePoint customer. Edit the "Confirm?" column.', '',
  '| # | Folder | Scan | Form name | Form email | Proposed customer | Cust email | dist | Confirm? |',
  '|---|--------|------|-----------|-----------|-------------------|-----------|------|----------|',
];
$n = 0;
foreach ( $entries as $e ) {
  $email = $e['email'] ? mb_strtolower( trim( $e['email'] ), 'UTF-8' ) : '';
  $norm  = normalize_name( $e['name'] );
  $matched = ( $email && isset( $by_email[ $email ] ) ) || count( $by_name[ $norm ] ?? [] ) === 1;
  if ( $matched ) { continue; }

  $n++;
  $cand = rank( $e['name'], $e['email'], $customers, 3 );
  $first = true;
  foreach ( $cand as $k => $score ) {
    $c = $customers[ $k ];
    $lines[] = sprintf( '| %s | %s | %s | %s | %s | #%s %s | %s | %d | |',
      $first ? (string) $n : '', $first ? $e['source'] : '', $first ? basename( $e['image'] ) : '',
      $first ? $e['name'] : '', $first ? ( $e['email'] ?: '' ) : '',
      $c['id'], $c['full'], $c['email'] ?: '—', $score );
    $first = false;
  }
}

$out = "$HERE/unmatched-proposals.md";
file_put_contents( $out, implode( "\n", $lines ) . "\n" );
echo "Unmatched: $n\nWrote: $out\n";
