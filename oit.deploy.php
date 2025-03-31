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
    1 => 1269, // Buff Portal
    2 => 1270, // Canvas
    3 => 1271, // Classroom Capture
    4 => 1272, // Computing Labs
    28 => 1292, // eduroam Secure Wireless
    5 => 1273, // Federated Identity Service
    6 => 1274, // Google Workspace
    7 => 1275, // Grouper
    8 => 1293, // iClicker
    9 => 1276, // Identity Manager
    10 => 1295, // Kaltura Rich Media Streaming
    11 => 1277, // Microsoft Office 365
    12 => 1278, // MyCUInfo
    14 => 1279, // OIT Data Centers
    15 => 1280, // Personal Capture
    16 => 1281, // PlayPosit
    17 => 1296, // Proctorio
    18 => 1282, // Qualtrics
    19 => 1283, // SensusAccess
    20 => 1297, // Sympa Email Lists
    21 => 1284, // Turnitin
    27 => 1285, // UCB Guest Wireless
    13 => 1286, // UCB Wireless
    22 => 1289, // VoiceThread
    23 => 1287, // VPN
    26 => 1290, // Wired Internet
    24 => 1291, // Zoom
    25 => 1294, // Other
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
