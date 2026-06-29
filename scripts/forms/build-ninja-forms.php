<?php
/**
 * Create the 4 consent forms as REAL Ninja Forms definitions, one per scan
 * grouping (folders 1-2, 3-6, 4, 5). Two granular field templates underlie them:
 *   - Template A (2-page, "2-lk"): no contact fields, fuller questionnaire
 *   - Template B (1-page, "1-lk"): includes e-mail + phone
 * Field labels/options are transcribed verbatim from the paper forms, so the
 * importer can map each transcribed answer onto a real form field.
 *
 * These are the IMPORT-OF-RECORD forms: the historical scans are imported as
 * native Ninja Forms submissions against them (import-consent-forms.php), with
 * every questionnaire field populated. New digital captures get their own forms.
 * Each form also carries an `originaaldokument` field holding the scan image URL.
 *
 * MUST run on the server — needs the ninja-forms plugin (absent from the local
 * checkout). Uses the model API + synchronous save() + manual cache rebuild;
 * import_form() is unusable from CLI (saves field settings via an async process
 * that never runs in a one-shot boot, so fields land blank).
 *
 *   php -d memory_limit=512M build-ninja-forms.php           # dry run
 *   php -d memory_limit=512M build-ninja-forms.php --commit  # create the forms
 *
 * Idempotent: delete-and-recreate by title, so re-runs converge to 4 clean forms.
 */

if ( php_sapi_name() !== 'cli' ) { exit( "CLI only.\n" ); }
$COMMIT = in_array( '--commit', $argv, true );

for ( $dir = __DIR__, $i = 0; $i < 8; $i++, $dir = dirname( $dir ) ) {
  if ( file_exists( "$dir/wp-load.php" ) ) { require "$dir/wp-load.php"; break; }
}
if ( ! function_exists( 'Ninja_Forms' ) ) {
  exit( "Ninja Forms not loaded — run this on the server where the plugin is active.\n" );
}

/* ---------- field builders (compact -> NF settings arrays) ---------- */
$order = 0;
$txt    = function ( string $key, string $label, bool $req = false ) use ( &$order ) {
  return [ 'type' => 'textbox', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => $req ? 1 : 0 ];
};
$area   = function ( string $key, string $label ) use ( &$order ) {
  return [ 'type' => 'textarea', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => 0 ];
};
$date   = function ( string $key, string $label, bool $req = false ) use ( &$order ) {
  return [ 'type' => 'date', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => $req ? 1 : 0 ];
};
$email  = function ( string $key, string $label, bool $req = false ) use ( &$order ) {
  return [ 'type' => 'email', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => $req ? 1 : 0 ];
};
$phone  = function ( string $key, string $label ) use ( &$order ) {
  return [ 'type' => 'phone', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => 0 ];
};
$opts   = function ( array $labels ): array {
  $out = []; $i = 0;
  foreach ( $labels as $l ) {
    $out[] = [ 'label' => $l, 'value' => sanitize_title( $l ) ?: ( 'o' . $i ), 'calc' => '', 'selected' => 0, 'order' => $i++ ];
  }
  return $out;
};
$radio  = function ( string $key, string $label, array $labels, bool $req = false ) use ( &$order, $opts ) {
  return [ 'type' => 'listradio', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => $req ? 1 : 0, 'options' => $opts( $labels ) ];
};
$checks = function ( string $key, string $label, array $labels ) use ( &$order, $opts ) {
  return [ 'type' => 'listcheckbox', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => 0, 'options' => $opts( $labels ) ];
};
$consent = function ( string $key, string $label ) use ( &$order ) {
  return [ 'type' => 'checkbox', 'key' => $key, 'label' => $label, 'order' => ++$order, 'required' => 1 ];
};
$html   = function ( string $key, string $label, string $content ) use ( &$order ) {
  return [ 'type' => 'html', 'key' => $key, 'label' => $label, 'order' => ++$order, 'default' => $content ];
};
$submit = function () use ( &$order ) {
  return [ 'type' => 'submit', 'key' => 'submit', 'label' => 'Esita', 'order' => ++$order ];
};

$CONTRA_A = 'südamestimulaatori kasutamine ja südame arütmia, rasked vereringehäired, kubeme- või kõhusong, '
  . 'tuberkuloos, kasvajad, kaugele arenenud ateroskleroos, tromboos, nahapõletik, psoriaas, epilepsia, sclerosis multiplex';
$CONTRA_B = '1.1 südamestimulaatori kasutamine ja südame rütmihäired<br>1.2 rasked vereringehäired<br>'
  . '1.3 kubeme- või kõhusong<br>1.4 tuberkuloos<br>1.5 kasvajad<br>1.6 kaugele arenenud ateroskleroos<br>'
  . '1.7 tromboos<br>1.8 nahapõletik<br>1.9 psoriaas<br>2.1 epilepsia<br>2.2 sclerosis multiplex';

/* ---------- Template A: 2-page form (folders 1-2, 3-6) ---------- */
function fields_template_a( $t, $a, $d, $r, $c, $consent, $h, $sub, $contra ): array {
  return [
    $t( 'eesnimi', 'Kliendi ees- ja perekonnanimi', true ),
    $d( 'synniaeg', 'Sünniaeg', true ),
    $h( 'vastunaidustused', 'Vastunäidustused, mille olemasolul ei tohi teenust kasutada (mitte lõplik nimekiri)', $contra ),
    $r( 'varem_ems', 'Kas oled varem EMS treeningul osalenud?', [ 'Ei', 'Jah' ] ),
    $t( 'varem_ems_millal', 'Millal viimati?' ),
    $r( 'vahe_48h', 'Olen teadlik, et EMS treeningute vahe peab olema miinimum 48 tundi.', [ 'Jah', 'Ei' ] ),
    $r( 'aktiivsus', 'Kui füüsiliselt aktiivne üldiselt oled?', [ 'Käin regulaarselt trennis (kõrge aktiivsus)', 'Trennis regulaarselt ei käi, kuid liigun piisavalt (keskmine aktiivsus)', 'Võrdlemisi vähe (madal aktiivsus)' ] ),
    $r( 'sudame_probleemid', 'Kas Sul on südamega probleeme?', [ 'Ei', 'Jah' ] ),
    $t( 'sudame_millised', 'Milliseid?' ),
    $r( 'yldine_seisund', 'Kuidas hindad enda üldist tervislikku seisundit ja enesetunnet praegu?', [ 'Hea', 'Keskmine', 'Halb' ] ),
    $c( 'vigastused', 'Kas Sul on olnud vigastusi, püsivat valu või muid kaebusi nendes piirkondades?', [ 'Kael', 'Õlad', 'Selg', 'Puusad', 'Põlved', 'Käed' ] ),
    $t( 'vigastused_tapsustus', 'Täpsustus' ),
    $a( 'muu_teave', 'Kas on veel midagi, millest sooviksid meid teavitada (mõni vigastus, kehaline või vaimne seisund, rasedus, viimase 6 kuu jooksul toimunud operatsioon, muu)?' ),
    $r( 'toitumine', 'Kuidas hindad enda toitumisharjumusi?', [ 'Hea', 'Keskmine', 'Halb' ] ),
    $c( 'eesmark', 'Mis on Sinu eesmärk treeninguga?', [ 'Kaalulangetus', 'Lihastoonuse tõstmine', 'Üldine füüsilise aktiivsuse tõstmine', 'Muu' ] ),
    $r( 'plaan_6k', 'Mis on Su järgneva 6 kuu füüsilise aktiivsuse plaan?', [ 'Käin regulaarselt trennis, et saavutada oma eesmärke', 'Olen katsetamas erinevaid trenne ja treenereid, et leida endale sobiv', 'Pole hetkel plaanis pühenduda, olen niisama ringi vaatamas', 'Muu' ] ),
    $r( 'kuidas_leidsid', 'Kuidas meid leidsid?', [ 'Olen varasem klient', 'Facebooki reklaam', 'Instagrami reklaam', 'Leidsin teenuse Stebby kaudu', 'Kuulsin sõbra/tuttava käest', 'Otsisin internetist', 'Muu' ] ),
    $consent( 'kinnitus', 'Kinnitan, et endale parimate teadmiste kohaselt ei ole mul loetletud vastunäidustusi ega esine muid tingimusi, mis võiksid takistada minu treeningul osalemist.' ),
    $d( 'kuupaev', 'Kuupäev' ),
    $t( 'allkiri', 'Allkiri' ),
    $t( 'treener', 'Treeneri nimi' ),
    $a( 'originaaldokument', 'Originaaldokument' ),
    $sub(),
  ];
}

/* ---------- Template B: 1-page form (folders 4, 5) ---------- */
function fields_template_b( $t, $a, $d, $e, $p, $r, $c, $consent, $h, $sub, $contra ): array {
  return [
    $t( 'eesnimi', 'Ees- ja perekonnanimi', true ),
    $d( 'synniaeg', 'Sünniaeg', true ),
    $e( 'email', 'E-mail', true ),
    $p( 'telefon', 'Telefon' ),
    $t( 'info_allikas', 'Kust said infot Yumefit Stuudio kohta?' ),
    $r( 'varem_ems', 'Kas Sa oled varem EMS trenni teinud?', [ 'Ei', 'Jah' ] ),
    $t( 'varem_ems_millal', 'Millal?' ),
    $r( 'yldine_seisund', 'Kuidas hindaksid enda üldist seisundit?', [ 'Hea', 'Keskmine', 'Halb' ] ),
    $r( 'trenni_sagedus', 'Kui tihti teed trenni?', [ 'Ei teegi', 'Harva', 'Tihti' ] ),
    $r( 'sudame_probleemid', 'Kas Sul on südamega probleeme?', [ 'Ei', 'Jah' ] ),
    $t( 'sudame_millised', 'Milliseid?' ),
    $c( 'vigastused', 'Kas Sul on olnud vigastusi või püsivat valu nendes piirkondades? Kui jah, siis palun täpsusta.', [ 'Kael', 'Õlad', 'Selg', 'Puusad', 'Põlved', 'Käed' ] ),
    $t( 'vigastused_tapsustus', 'Täpsustus' ),
    $a( 'muu_teave', 'Kas on veel midagi, millest sooviksid teavitada meid: mõni vigastus, kehaline või vaimne seisund, lapseootus, viimase 6 kuu jooksul toimunud operatsioon?' ),
    $h( 'vastunaidustused', 'Vastunäidustused, mille olemasolul ei tohi teenust kasutada, on järgnevad', $contra ),
    $consent( 'kinnitus', 'Käesolevaga kinnitan oma allkirjaga, et mind on teavitatud vastunäidustustest.' ),
    $t( 'allkiri', 'Allkiri' ),
    $d( 'kuupaev', 'Kuupäev' ),
    $a( 'originaaldokument', 'Originaaldokument' ),
    $sub(),
  ];
}

/* ---------- the four forms ---------- */
$FORMS = [
  [ 'title' => 'Kliendi teavitamine ja nõusolek (2-lk, I)',  'tpl' => 'A' ],
  [ 'title' => 'Kliendi teavitamine ja nõusolek (2-lk, II)', 'tpl' => 'A' ],
  [ 'title' => 'Kliendi teavitamine ja nõusolek (1-lk, I)',  'tpl' => 'B' ],
  [ 'title' => 'Kliendi teavitamine ja nõusolek (1-lk, II)', 'tpl' => 'B' ],
];

global $wpdb;
$titles = array_column( $FORMS, 'title' );

echo $COMMIT ? "MODE: COMMIT\n" : "MODE: DRY RUN (pass --commit to create)\n";

// Delete-and-recreate: removes any prior version of these forms so re-runs
// converge to exactly four clean forms.
if ( $COMMIT ) {
  foreach ( Ninja_Forms()->form()->get_forms() as $f ) {
    if ( in_array( $f->get_setting( 'title' ), $titles, true ) ) {
      echo sprintf( "  delete existing #%d  %s\n", $f->get_id(), $f->get_setting( 'title' ) );
      ( new NF_Database_Models_Form( $wpdb, $f->get_id() ) )->delete();
    }
  }
}

$actions = [
  [ 'type' => 'save', 'label' => 'Save Submission', 'active' => 1 ],
  [ 'type' => 'successmessage', 'label' => 'Success Message', 'active' => 1,
    'message' => '<p>Aitäh! Sinu nõusolek on salvestatud.</p>' ],
];

foreach ( $FORMS as $spec ) {
  $order  = 0;
  $fields = $spec['tpl'] === 'A'
    ? fields_template_a( $txt, $area, $date, $radio, $checks, $consent, $html, $submit, $CONTRA_A )
    : fields_template_b( $txt, $area, $date, $email, $phone, $radio, $checks, $consent, $html, $submit, $CONTRA_B );

  echo sprintf( "  %s  %-42s  template %s, %d fields\n", $COMMIT ? 'CREATE' : 'would create', $spec['title'], $spec['tpl'], count( $fields ) );
  if ( ! $COMMIT ) { continue; }

  $form = new NF_Database_Models_Form( $wpdb, '' );
  $form->update_settings( [ 'title' => $spec['title'], 'default_label_pos' => 'above', 'show_title' => 1, 'created_at' => current_time( 'mysql' ) ] )->save();
  $fid = (int) $form->get_id();

  $cache = [ 'id' => $fid, 'fields' => [], 'actions' => [], 'settings' => $form->get_settings() ];

  foreach ( $fields as $fs ) {
    $field = new NF_Database_Models_Field( $wpdb, '', $fid );
    $field->update_settings( $fs )->save();
    $cache['fields'][] = [ 'id' => $field->get_id(), 'settings' => $field->get_settings() ];
  }

  foreach ( $actions as $as ) {
    $action = new NF_Database_Models_Action( $wpdb, '', $fid );
    $action->update_settings( $as )->save();
    $cache['actions'][] = [ 'id' => $action->get_id(), 'settings' => $action->get_settings() ];
  }

  WPN_Helper::update_nf_cache( $fid, $cache );
  echo sprintf( "     -> created form #%d (%d fields, %d actions)\n", $fid, count( $cache['fields'] ), count( $cache['actions'] ) );
}

echo "\nDone.\n";
if ( ! $COMMIT ) { echo "Dry run only — re-run with --commit.\n"; }
