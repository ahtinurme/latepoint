<?php
/**
 * Build and wire the WEB consent form for first-time EMS bookings.
 *
 * Based on the 2-page granular form (#7, template A) but for online use:
 *   - drops the "Treeneri nimi" field,
 *   - replaces the "Allkiri" textbox with a drawn (handwritten) Signature field,
 *   - adds the hidden lp_nf_order / lp_nf_token fields the bridge fills + reads.
 *
 * Then wires delivery + linking via the existing latepoint-ninja-forms bridge:
 *   - a WP page holding [latepoint_ninja_form] (created if missing),
 *   - bridge settings ninja_forms_form_id / ninja_forms_page_id, auto-append OFF
 *     (the link rides only the esmakordne email, via its {{order_ninja_form_link}}),
 *   - per-service link toggle ON for the two first-time EMS services (#1, #2),
 *   - {{order_ninja_form_link}} appended to the CUSTOMER email of the two
 *     "Esmakordne EMS" booking automations (#2, #3).
 *
 * On submission the bridge resolves lp_nf_order -> order -> customer and stamps
 * _latepoint_customer_id / _latepoint_order_id on the native sub, so it lists on
 * the customer exactly like the imported paper forms.
 *
 * Run on the server (or locally with the CLI shim — see [[local-cli-against-prod-db]]):
 *   YUMEFIT_CLI_SHIM=1 php -d memory_limit=512M setup_web_consent_form.php           # dry run
 *   YUMEFIT_CLI_SHIM=1 php -d memory_limit=512M setup_web_consent_form.php --commit   # apply
 *
 * Idempotent: form recreated by title, page/settings/toggles upserted, link appended once.
 */

if ( php_sapi_name() !== 'cli' ) { exit( "CLI only.\n" ); }
$COMMIT = in_array( '--commit', $argv, true );

for ( $dir = __DIR__, $i = 0; $i < 8; $i++, $dir = dirname( $dir ) ) {
  if ( file_exists( "$dir/wp-load.php" ) ) { require "$dir/wp-load.php"; break; }
}
if ( ! function_exists( 'Ninja_Forms' ) || ! class_exists( 'OsSettingsHelper' ) ) {
  exit( "Ninja Forms / LatePoint not loaded — run on the server (or with YUMEFIT_CLI_SHIM=1).\n" );
}

const FORM_TITLE   = 'Kliendi teavitamine ja nõusolek (veebivorm)';
const EMS_SERVICES = [ 1, 2 ];          // first-time EMS services
const EMS_PROCESSES = [ 2, 3 ];         // "Esmakordne EMS" booking automations
const LINK_MARKER  = '{{order_ninja_form_link}}';

echo $COMMIT ? "MODE: COMMIT\n\n" : "MODE: DRY RUN (pass --commit to apply)\n\n";

/* ===========================================================================
 * 1) Build the web form (template A, minus treener, allkiri -> signature, + hidden fields)
 * ========================================================================= */
$order = 0;
$txt   = fn( $key, $label, $req = false ) => [ 'type' => 'textbox', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => $req ? 1 : 0 ];
$area  = fn( $key, $label ) => [ 'type' => 'textarea', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => 0 ];
$date  = fn( $key, $label, $req = false ) => [ 'type' => 'date', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => $req ? 1 : 0 ];
$opts  = function ( array $labels ): array {
  $out = []; $i = 0;
  foreach ( $labels as $l ) { $out[] = [ 'label' => $l, 'value' => sanitize_title( $l ) ?: ( 'o' . $i ), 'calc' => '', 'selected' => 0, 'order' => $i++ ]; }
  return $out;
};
$radio = fn( $key, $label, $labels ) => [ 'type' => 'listradio', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => 0, 'options' => $opts( $labels ) ];
$checks = fn( $key, $label, $labels ) => [ 'type' => 'listcheckbox', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => 0, 'options' => $opts( $labels ) ];
$consent = fn( $key, $label ) => [ 'type' => 'checkbox', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => 1 ];
$html  = fn( $key, $label, $content ) => [ 'type' => 'html', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'default' => $content ];
$email = fn( $key, $label, $req = false ) => [ 'type' => 'email', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => $req ? 1 : 0 ];
$phone = fn( $key, $label, $req = false ) => [ 'type' => 'phone', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => $req ? 1 : 0 ];
$hidden = fn( $key ) => [ 'type' => 'hidden', 'key' => $key, 'label' => $key, 'order' => ++$GLOBALS['order'] ];
// "Muu, palun täpsusta" free-text — placed right after a radio/checkbox whose last option is "Muu";
// hidden by default and revealed via CSS :has() when that field's "Muu" option is selected.
$muu_detail = fn( $key ) => [ 'type' => 'textbox', 'key' => $key, 'label' => 'Muu, palun täpsusta', 'order' => ++$GLOBALS['order'], 'required' => 0, 'container_class' => 'lp-muu-detail' ];
// Date field defaulting to today (NF "Default To Current Date").
$date_today = fn( $key, $label ) => [ 'type' => 'date', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => 0, 'date_mode' => 'date_only', 'date_default' => 1 ];
$signature = fn( $key, $label ) => [
  'type' => 'signature', 'key' => $key, 'label' => $label, 'order' => ++$GLOBALS['order'], 'required' => 1,
  'signature_method' => 'drawn', 'canvas_width' => 400, 'canvas_height' => 150,
  'pen_color' => '#000000', 'background_color' => '#ffffff', 'drawn_placeholder' => 'Allkirjasta siia',
];
$submit = fn() => [ 'type' => 'submit', 'key' => 'submit', 'label' => 'Esita', 'order' => ++$GLOBALS['order'] ];

$CONTRA = '<strong>EMSi vastunäidustused:</strong><br>'
  . 'südamestimulaatori kasutamine ja südame arütmia, rasked vereringehäired, kubeme- või kõhusong, '
  . 'tuberkuloos, kasvajad, kaugele arenenud ateroskleroos, tromboos, nahapõletik, psoriaas, epilepsia, sclerosis multiplex';

$fields = [
  // Identity block — required so the submitter (esp. the 2nd "kahekesi" person) can be
  // matched to a LatePoint customer by email, or a new account created. Prefilled from the
  // booker on the primary link; blank on the guest link.
  $txt( 'eesnimi', 'Eesnimi', true ),
  $txt( 'perekonnanimi', 'Perekonnanimi', true ),
  $email( 'email', 'E-mail', true ),
  $phone( 'telefon', 'Telefon', true ),
  $date( 'synniaeg', 'Sünniaeg', true ),
  $radio( 'varem_ems', 'Kas oled varem EMS treeningul osalenud?', [ 'Ei', 'Jah' ] ),
  $txt( 'varem_ems_millal', 'Millal viimati?' ),
  $radio( 'vahe_48h', 'Olen teadlik, et EMS treeningute vahe peab olema miinimum 48 tundi.', [ 'Jah', 'Ei' ] ),
  $radio( 'aktiivsus', 'Kui füüsiliselt aktiivne üldiselt oled?', [ 'Käin regulaarselt trennis (kõrge aktiivsus)', 'Trennis regulaarselt ei käi, kuid liigun piisavalt (keskmine aktiivsus)', 'Võrdlemisi vähe (madal aktiivsus)' ] ),
  $radio( 'sudame_probleemid', 'Kas Sul on südamega probleeme?', [ 'Ei', 'Jah' ] ),
  $txt( 'sudame_millised', 'Milliseid?' ),
  $radio( 'yldine_seisund', 'Kuidas hindad enda üldist tervislikku seisundit ja enesetunnet praegu?', [ 'Hea', 'Keskmine', 'Halb' ] ),
  $checks( 'vigastused', 'Kas Sul on olnud vigastusi, püsivat valu või muid kaebusi nendes piirkondades?', [ 'Kael', 'Õlad', 'Selg', 'Puusad', 'Põlved', 'Käed' ] ),
  $txt( 'vigastused_tapsustus', 'Täpsustus' ),
  $area( 'muu_teave', 'Kas on veel midagi, millest sooviksid meid teavitada (mõni vigastus, kehaline või vaimne seisund, rasedus, viimase 6 kuu jooksul toimunud operatsioon, muu)?' ),
  $radio( 'toitumine', 'Kuidas hindad enda toitumisharjumusi?', [ 'Hea', 'Keskmine', 'Halb' ] ),
  $checks( 'eesmark', 'Mis on Sinu eesmärk treeninguga?', [ 'Kaalulangetus', 'Lihastoonuse tõstmine', 'Üldine füüsilise aktiivsuse tõstmine', 'Muu' ] ),
  $muu_detail( 'eesmark_muu' ),
  $radio( 'plaan_6k', 'Mis on Su järgneva 6 kuu füüsilise aktiivsuse plaan?', [ 'Käin regulaarselt trennis, et saavutada oma eesmärke', 'Olen katsetamas erinevaid trenne ja treenereid, et leida endale sobiv', 'Pole hetkel plaanis pühenduda, olen niisama ringi vaatamas', 'Muu' ] ),
  $muu_detail( 'plaan_6k_muu' ),
  $radio( 'kuidas_leidsid', 'Kuidas meid leidsid?', [ 'Olen varasem klient', 'Facebooki reklaam', 'Instagrami reklaam', 'Leidsin teenuse Stebby kaudu', 'Kuulsin sõbra/tuttava käest', 'Otsisin internetist', 'Muu' ] ),
  $muu_detail( 'kuidas_leidsid_muu' ),
  // Contraindications sit right above the consent checkbox — its "loetletud vastunäidustusi" refers to this list.
  $html( 'vastunaidustused', 'Vastunäidustused, mille olemasolul ei tohi teenust kasutada (mitte lõplik nimekiri)', $CONTRA ),
  $consent( 'kinnitus', 'Kinnitan, et endale parimate teadmiste kohaselt ei ole mul loetletud vastunäidustusi ega esine muid tingimusi, mis võiksid takistada minu treeningul osalemist.' ),
  $date_today( 'kuupaev', 'Kuupäev' ),
  $signature( 'allkiri', 'Allkiri' ),
  $hidden( 'lp_nf_order' ),
  $hidden( 'lp_nf_token' ),
  $submit(),
];

global $wpdb;
echo "FORM: \"" . FORM_TITLE . "\" — " . count( $fields ) . " fields (incl. drawn signature + 2 hidden)\n";

$form_id = null;
foreach ( Ninja_Forms()->form()->get_forms() as $f ) {
  if ( $f->get_setting( 'title' ) === FORM_TITLE ) {
    echo "  delete existing #" . $f->get_id() . "\n";
    if ( $COMMIT ) { ( new NF_Database_Models_Form( $wpdb, $f->get_id() ) )->delete(); }
  }
}

if ( $COMMIT ) {
  $form = new NF_Database_Models_Form( $wpdb, '' );
  $form->update_settings( [ 'title' => FORM_TITLE, 'default_label_pos' => 'above', 'show_title' => 1, 'created_at' => current_time( 'mysql' ) ] )->save();
  $form_id = (int) $form->get_id();
  $cache = [ 'id' => $form_id, 'fields' => [], 'actions' => [], 'settings' => $form->get_settings() ];
  foreach ( $fields as $fs ) {
    $field = new NF_Database_Models_Field( $wpdb, '', $form_id );
    $field->update_settings( $fs )->save();
    $cache['fields'][] = [ 'id' => $field->get_id(), 'settings' => $field->get_settings() ];
  }
  foreach ( [
    [ 'type' => 'save', 'label' => 'Save Submission', 'active' => 1 ],
    [ 'type' => 'successmessage', 'label' => 'Success Message', 'active' => 1, 'message' => '<p>Aitäh! Sinu nõusolek on salvestatud.</p>' ],
  ] as $as ) {
    $action = new NF_Database_Models_Action( $wpdb, '', $form_id );
    $action->update_settings( $as )->save();
    $cache['actions'][] = [ 'id' => $action->get_id(), 'settings' => $action->get_settings() ];
  }
  WPN_Helper::update_nf_cache( $form_id, $cache );
  echo "  -> created form #$form_id\n";
}

/* ===========================================================================
 * 2) Form page holding the [latepoint_ninja_form] shortcode
 * ========================================================================= */
$page_id = (int) OsSettingsHelper::get_settings_value( 'ninja_forms_page_id', 0 );
if ( ! ( $page_id && get_post( $page_id ) ) ) {
  $hit = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status!='trash' AND post_content LIKE '%[latepoint_ninja_form]%' LIMIT 1" );
  $page_id = (int) $hit;
}
if ( $page_id ) {
  echo "PAGE: reuse #$page_id (" . get_permalink( $page_id ) . ")\n";
} else {
  echo "PAGE: create new with [latepoint_ninja_form]\n";
  if ( $COMMIT ) {
    $page_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'EMS nõusoleku vorm', 'post_content' => '[latepoint_ninja_form]' ] );
    echo "  -> created page #$page_id (" . get_permalink( $page_id ) . ")\n";
  }
}

/* ===========================================================================
 * 3) Bridge settings + 4) per-service toggle
 * ========================================================================= */
if ( $COMMIT ) {
  OsSettingsHelper::save_setting_by_name( 'ninja_forms_form_id', $form_id );
  OsSettingsHelper::save_setting_by_name( 'ninja_forms_page_id', $page_id );
  OsSettingsHelper::save_setting_by_name( 'ninja_forms_auto_append_email', 'off' );
  echo "SETTINGS: ninja_forms_form_id=$form_id, ninja_forms_page_id=$page_id, auto_append=off\n";
  foreach ( EMS_SERVICES as $sid ) { OsMetaHelper::save_service_meta_by_key( 'ninja_form_enabled', LATEPOINT_VALUE_ON, $sid ); }
  echo "TOGGLE: ninja_form_enabled=on for services " . implode( ',', EMS_SERVICES ) . "\n";
} else {
  echo "SETTINGS: would set form_id, page_id, auto_append=off; toggle services " . implode( ',', EMS_SERVICES ) . "\n";
}

/* ===========================================================================
 * 5) Append the form link to the CUSTOMER email of the Esmakordne EMS automations
 * ========================================================================= */
// Primary link (booker) goes on both automations. The 2nd-person guest link goes on the kahekesi
// one only (service #2 / process #3), keyed by GUEST_MARKER so re-runs don't duplicate it.
const GUEST_MARKER    = '{{order_ninja_form_guest_link}}';
$primary_block = "\n\nPalun täida enne esimest treeningut nõusoleku vorm: " . LINK_MARKER . "\n";
$guest_block   = "\n\nTeine osaleja täidab enda nõusoleku vormi siin: " . GUEST_MARKER . "\n";

// Append $block to the customer email's content, once (guarded by $marker being absent).
function inject_block( array $tree, string $block, string $marker, int &$hits ): array {
  foreach ( $tree as $k => $node ) {
    if ( ! is_array( $node ) ) { continue; }
    if ( ( $node['type'] ?? '' ) === 'action' && ( $node['settings']['type'] ?? '' ) === 'send_email'
         && isset( $node['settings']['settings'] ) && is_array( $node['settings']['settings'] ) ) {
      $s = $node['settings']['settings'];
      if ( stripos( (string) ( $s['to_email'] ?? '' ), 'customer' ) !== false
           && strpos( (string) ( $s['content'] ?? '' ), $marker ) === false ) {
        $node['settings']['settings']['content'] = (string) ( $s['content'] ?? '' ) . $block;
        $hits++;
      }
    }
    $tree[ $k ] = inject_block( $node, $block, $marker, $hits ); // recurse into children
  }
  return $tree;
}

const KAHEKESI_PROCESS = 3; // "Esmakordne EMS kahekesi" (service #2) — the only 2-person one
foreach ( EMS_PROCESSES as $pid ) {
  $proc = new OsProcessModel( $pid );
  if ( ! $proc->id ) { echo "PROCESS #$pid: not found, skipping\n"; continue; }
  $tree = json_decode( $proc->actions_json, true );
  if ( ! is_array( $tree ) ) { echo "PROCESS #$pid: unreadable actions_json, skipping\n"; continue; }
  $hits = 0;
  $tree = inject_block( $tree, $primary_block, LINK_MARKER, $hits );
  if ( $pid === KAHEKESI_PROCESS ) {
    $tree = inject_block( $tree, $guest_block, GUEST_MARKER, $hits ); // 2nd-person link
  }
  echo "PROCESS #$pid \"{$proc->name}\": " . ( $hits ? "added $hits link block(s)" : "links already present / no customer email" ) . "\n";
  if ( $COMMIT && $hits ) {
    $proc->actions_json = wp_json_encode( $tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( ! $proc->save() ) { echo "  SAVE FAILED: " . implode( '; ', $proc->get_error_messages() ) . "\n"; }
  }
}

echo "\n" . ( $COMMIT ? "Done.\n" : "Dry run only — re-run with --commit.\n" );
