<?php

/**
 * @file
 * Deploy hooks for OIT.
 */

/**
 * Dashboard taxonomy add.
 */
function oit_deploy_10000_dashtax() {
  $service_links = [
    'Buff Portal' => '21566',
    'Canvas' => '19026',
    'Classroom Capture' => '418',
    'Computing Labs' => '413',
    'Federated Identity Service' => '3174',
    'Google Workspace' => '10617',
    'Grouper' => '16743',
    'Identity Manager' => '1169',
    'Microsoft 365' => '12589',
    'MyCUInfo' => 'https://mycuinfo.colorado.edu',
    'OIT Data Centers' => '254',
    'Personal Capture' => '25106',
    'PlayPosit' => '21061',
    'Qualtrics' => '8615',
    'SensusAccess' => '16521',
    'Turnitin' => '2323',
    'UCB Guest Wireless' => '1010',
    'UCB Wireless' => '612',
    'VPN' => '573',
    'Voice Communications' => '1033',
    'VoiceThread' => '10101',
    'Wired Internet' => '737',
    'Zoom' => '15005',
    'eduroam Secure Wireless' => '15585',
    'iClicker' => '243',
    'Other' => '',
  ];

  foreach ($service_links as $service => $service_id) {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create([
      'vid' => 'service_dashboard_category',
      'name' => $service,
      'field_sa_dash_cat_node_id' => $service_id,
    ]);
    $term->save();
  }

}

/**
 * Update dash tax from current dashboard categories.
 */
function oit_deploy_10001_mapcats() {
  $dash_cat = [
  // Buff Portal.
    1 => 1269,
  // Canvas.
    2 => 1270,
  // Classroom Capture.
    3 => 1271,
  // Computing Labs.
    4 => 1272,
  // Eduroam Secure Wireless.
    28 => 1292,
  // Federated Identity Service.
    5 => 1273,
  // Google Workspace.
    6 => 1274,
  // Grouper.
    7 => 1275,
  // iClicker.
    8 => 1293,
  // Identity Manager.
    9 => 1276,
  // Kaltura Rich Media Streaming.
    10 => 1295,
  // Microsoft Office 365.
    11 => 1277,
  // MyCUInfo.
    12 => 1278,
  // OIT Data Centers.
    14 => 1279,
  // Personal Capture.
    15 => 1280,
  // PlayPosit.
    16 => 1281,
  // Proctorio.
    17 => 1296,
  // Qualtrics.
    18 => 1282,
  // SensusAccess.
    19 => 1283,
  // Sympa Email Lists.
    20 => 1297,
  // Turnitin.
    21 => 1284,
  // UCB Guest Wireless.
    27 => 1285,
  // UCB Wireless.
    13 => 1286,
  // VoiceThread.
    22 => 1289,
  // VPN.
    23 => 1287,
  // Wired Internet.
    26 => 1290,
  // Zoom.
    24 => 1291,
  // Other.
    25 => 1294,
  ];

  $query = \Drupal::database()->select('node__field_service_dashboard_category', 'fdc');
  $query->fields('fdc', ['entity_id', 'revision_id', 'delta', 'field_service_dashboard_category_value']);
  $result = $query->execute()->fetchAll();

  foreach ($result as $row) {
    $mysql_insert = \Drupal::database()->insert('node__field_service_alert_dash_cat');
    $mysql_insert->fields([
      'bundle' => 'service_alert',
      'deleted' => 0,
      'entity_id' => $row->entity_id,
      'revision_id' => $row->revision_id,
      'langcode' => 'en',
      'delta' => $row->delta,
      'field_service_alert_dash_cat_target_id' => $dash_cat[$row->field_service_dashboard_category_value],
    ]);
    $mysql_insert->execute();

    $mysql_insert = \Drupal::database()->insert('node_revision__field_service_alert_dash_cat');
    $mysql_insert->fields([
      'bundle' => 'service_alert',
      'deleted' => 0,
      'entity_id' => $row->entity_id,
      'revision_id' => $row->revision_id,
      'langcode' => 'en',
      'delta' => $row->delta,
      'field_service_alert_dash_cat_target_id' => $dash_cat[$row->field_service_dashboard_category_value],
    ]);
    $mysql_insert->execute();
  }

}

/**
 * Update dashboard category weights.
 */
function oit_deploy_10002_dashboard_weight() {
  $term_update = [
    1286 => 0,
    1290 => 1,
    1270 => 2,
    1291 => 3,
    1269 => 4,
    1276 => 5,
    1277 => 6,
    1274 => 7,
    1279 => 8,
  ];

  foreach ($term_update as $tid => $weight) {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
    if ($term) {
      $term->set('field_weight', $weight);
      $term->save();
    }
  }
}
