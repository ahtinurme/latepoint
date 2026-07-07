<?php
/**
 * Move the paper-consent scans into a protected uploads dir and point the
 * Ninja Forms submissions at it.
 *
 * The original import sideloaded scans into the public media library — and ran
 * locally, so prod never got the files (the cabinet showed broken images), and
 * public URLs would have exposed the documents to anyone anyway. This script:
 *
 *   1. copies each sub's source scans (scripts/forms/1–6) into
 *      wp-content/uploads/lp-consent/ guarded by a deny-all .htaccess
 *   2. rewrites each sub's `originaaldokument` field to bare basenames — the
 *      latepoint-ninja-forms addon now renders them through an authenticated
 *      admin-ajax endpoint (owner customer or admin/agent only)
 *   3. deletes the orphaned public media-library attachment rows
 *
 * Boots SHORTINIT ($wpdb only), so it runs from the local checkout against the
 * prod DB without the full-stack boot fatals. Dry run by default:
 *
 *   php protect-consent-scans.php            # report only, writes nothing
 *   php protect-consent-scans.php --commit   # copy files + write DB
 *
 * After --commit: upload wp-content/uploads/lp-consent/ to the server at the
 * same path, and deploy the updated latepoint-ninja-forms plugin.
 *
 * Idempotent: basename values re-resolve to the same sources on re-run.
 */

if ( php_sapi_name() !== 'cli' ) {
  exit( "CLI only.\n" );
}

$COMMIT = in_array( '--commit', $argv, true );
$HERE   = __DIR__;

$wp_load = null;
$root    = null;
for ( $dir = $HERE, $i = 0; $i < 8; $i++, $dir = dirname( $dir ) ) {
  if ( file_exists( "$dir/wp-load.php" ) ) { $wp_load = "$dir/wp-load.php"; $root = $dir; break; }
}
if ( ! $wp_load ) { exit( "wp-load.php not found.\n" ); }
define( 'SHORTINIT', true );
require $wp_load;
global $wpdb;

$dest = "$root/wp-content/uploads/lp-consent";
echo $COMMIT ? "MODE: COMMIT\n" : "MODE: DRY RUN (pass --commit to apply)\n";

if ( $COMMIT ) {
  if ( ! is_dir( $dest ) ) { mkdir( $dest, 0755, true ); }
  file_put_contents( "$dest/.htaccess",
    "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
  file_put_contents( "$dest/index.html", '' );
}

/* originaaldokument field id per form */
$by_form = [];
foreach ( $wpdb->get_results( "SELECT id, parent_id FROM {$wpdb->prefix}nf3_fields WHERE `key` = 'originaaldokument'" ) as $f ) {
  $by_form[ (int) $f->parent_id ] = (int) $f->id;
}
if ( ! $by_form ) { exit( "No originaaldokument fields found — wrong DB?\n" ); }

/* source scan index: basename -> absolute path (unique across folders 1-6) */
$sources = [];
foreach ( [ '1', '2', '3', '4', '5', '6' ] as $folder ) {
  foreach ( glob( "$HERE/$folder/IMG_*" ) as $p ) { $sources[ basename( $p ) ] = $p; }
}
echo count( $sources ) . " source scans indexed.\n";

$subs = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_yumefit_paper_sub'" );
echo count( $subs ) . " paper submissions found.\n\n";

$fixed = 0; $problems = 0;
foreach ( $subs as $sub_id ) {
  $form_id = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_form_id'", $sub_id ) );
  $field_id = $by_form[ $form_id ] ?? 0;
  if ( ! $field_id ) { echo "  ?? sub $sub_id: no originaaldokument field on form $form_id\n"; $problems++; continue; }

  $meta_key = "_field_$field_id";
  $value = (string) $wpdb->get_var( $wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $sub_id, $meta_key ) );

  $names = []; $bad = false;
  foreach ( preg_split( '/\R+/', $value ) as $line ) {
    $line = trim( $line );
    if ( $line === '' ) { continue; }
    // stored: media URL of the "-scaled" copy, possibly with a WP "-1" dedup suffix
    // (or already a basename on re-run) -> source basename
    $name = str_replace( '-scaled', '', basename( (string) ( parse_url( $line, PHP_URL_PATH ) ?: $line ) ) );
    $name = preg_replace( '/-\d+(\.\w+)$/', '$1', $name );
    if ( empty( $sources[ $name ] ) ) { echo "  !! sub $sub_id: no source scan for '$line'\n"; $bad = true; break; }
    $names[] = $name;
  }
  if ( $bad ) { $problems++; continue; }
  if ( ! $names ) { echo "  ?? sub $sub_id: empty originaaldokument value\n"; $problems++; continue; }

  echo sprintf( "  sub %-6d -> %s\n", $sub_id, implode( ' + ', $names ) );
  $fixed++;
  if ( ! $COMMIT ) { continue; }

  foreach ( $names as $name ) { copy( $sources[ $name ], "$dest/$name" ); }
  $wpdb->update( $wpdb->postmeta, [ 'meta_value' => implode( "\n", $names ) ], [ 'post_id' => $sub_id, 'meta_key' => $meta_key ] );
}

/* orphaned public media-library copies from the original import */
$orphans = $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_yumefit_consent_src'" );
echo "\n" . count( $orphans ) . " orphaned media-library attachments (broken public copies).\n";
if ( $COMMIT && $orphans ) {
  $in = implode( ',', array_map( 'intval', $orphans ) );
  $deleted  = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($in) AND post_type = 'attachment'" );
  $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)" );
  echo "  deleted $deleted attachment posts (+ their meta).\n";
}

echo "\n==== SUMMARY ====\n";
echo "resolved: $fixed / " . count( $subs ) . "   problems: $problems\n";
if ( $COMMIT ) {
  echo "\nNext: upload wp-content/uploads/lp-consent/ to the server (same path)\n";
  echo "and deploy the updated latepoint-ninja-forms plugin.\n";
} else {
  echo "\nDry run only. Re-run with --commit to write.\n";
}
