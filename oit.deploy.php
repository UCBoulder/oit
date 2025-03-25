<?php

/**
 * @file
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
    'Kaltura Rich Media Streaming' => '3984',
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
