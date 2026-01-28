<?php

/**
 * @file
 * Deploy hooks for OIT.
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

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
 * Helper function to replicate a menu tree from main menu to a new menu.
 *
 * @param string $menu_id
 *   The machine name for the new menu.
 * @param string $menu_label
 *   The human-readable label for the new menu.
 * @param string $menu_description
 *   The description for the new menu.
 * @param int $root_id
 *   The menu link content ID to use as the root for replication.
 *
 * @return int
 *   The total count of replicated menu items.
 */
function oit_replicate_menu_tree($menu_id, $menu_label, $menu_description, $root_id) {
  $entity_type_manager = \Drupal::entityTypeManager();
  $menu_storage = $entity_type_manager->getStorage('menu');
  $menu_link_storage = $entity_type_manager->getStorage('menu_link_content');

  // Create new menu.
  $menu = $menu_storage->create([
    'id' => $menu_id,
    'label' => $menu_label,
    'description' => $menu_description,
  ]);
  $menu->save();

  // Map to track old ID -> new ID for parent relationships.
  $id_map = [];
  $total_count = 0;

  // Temporarily disable pathauto to avoid entity errors.
  $pathauto_state = NULL;
  if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
    $pathauto_state = \Drupal::configFactory()->getEditable('pathauto.settings')->get('enabled');
    \Drupal::configFactory()->getEditable('pathauto.settings')->set('enabled', FALSE)->save();
  }

  // Recursive function to replicate menu items and their children.
  $replicate_menu_tree = function($source_id, $new_parent = '') use (&$replicate_menu_tree, $menu_link_storage, &$id_map, &$total_count, $menu_id) {
    $source_link = $menu_link_storage->load($source_id);

    if (!$source_link) {
      return;
    }

    // Create new menu link in the new menu.
    $new_link = MenuLinkContent::create([
      'title' => $source_link->getTitle(),
      'link' => $source_link->get('link')->getValue(),
      'menu_name' => $menu_id,
      'weight' => $source_link->getWeight(),
      'enabled' => $source_link->isEnabled(),
      'expanded' => $source_link->isExpanded(),
      'parent' => $new_parent,
    ]);

    // Copy description if it exists.
    if ($source_link->hasField('description') && !$source_link->get('description')->isEmpty()) {
      $new_link->set('description', $source_link->get('description')->getValue());
    }

    // Save with error handling for pathauto issues.
    try {
      $new_link->save();
      $new_id = $new_link->id();
      $new_uuid = $new_link->uuid();
      $id_map[$source_id] = $new_id;
      $total_count++;
    }
    catch (\TypeError $e) {
      // Skip this item if we get a Pathauto TypeError.
      \Drupal::logger('oit_update')->warning('Skipped menu link @title (ID: @id) due to error: @message', [
        '@title' => $source_link->getTitle(),
        '@id' => $source_id,
        '@message' => $e->getMessage(),
      ]);
      return;
    }
    catch (\Exception $e) {
      // Log other exceptions but continue.
      \Drupal::logger('oit_update')->warning('Error saving menu link @title (ID: @id): @message', [
        '@title' => $source_link->getTitle(),
        '@id' => $source_id,
        '@message' => $e->getMessage(),
      ]);
      return;
    }

    // Get UUID of source link to find its children.
    $source_uuid = $source_link->uuid();

    // Find all children of this menu item.
    $database = \Drupal::database();
    $query = $database->select('menu_link_content_data', 'mlcd');
    $query->fields('mlcd', ['id']);
    $query->condition('mlcd.parent', 'menu_link_content:' . $source_uuid);
    $query->orderBy('mlcd.weight');
    $query->orderBy('mlcd.id');
    $child_ids = $query->execute()->fetchCol();

    // Recursively replicate each child with UUID-based parent.
    foreach ($child_ids as $child_id) {
      $replicate_menu_tree($child_id, 'menu_link_content:' . $new_uuid);
    }
  };

  // Start replication from the root.
  $replicate_menu_tree($root_id);

  // Re-enable pathauto if it was enabled.
  if ($pathauto_state !== NULL) {
    \Drupal::configFactory()->getEditable('pathauto.settings')->set('enabled', $pathauto_state)->save();
  }

  return $total_count;
}

/**
 * Helper function to update nodes' menu links from main menu to new menus.
 *
 * @param array $menu_mappings
 *   Array of menu mappings with 'old_root_id' and 'new_menu_id'.
 *
 * @return array
 *   Statistics about updated menu links.
 */
function oit_update_node_menu_links(array $menu_mappings) {
  $menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $stats = [];
  
  // Temporarily disable pathauto to avoid entity errors during deletion.
  $pathauto_state = NULL;
  if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
    $pathauto_state = \Drupal::configFactory()->getEditable('pathauto.settings')->get('enabled');
    \Drupal::configFactory()->getEditable('pathauto.settings')->set('enabled', FALSE)->save();
  }
  
  foreach ($menu_mappings as $mapping) {
    $old_root_id = $mapping['old_root_id'];
    $new_menu_id = $mapping['new_menu_id'];
    $deleted_count = 0;
    
    // Get all menu links under the old root in main menu.
    $old_root = $menu_link_storage->load($old_root_id);
    if (!$old_root) {
      continue;
    }
    
    $old_root_uuid = $old_root->uuid();
    
    // Find all descendants of this root in main menu recursively.
    $database = \Drupal::database();
    
    // Get all menu links that belong to this tree in main menu.
    $all_links_to_delete = [];
    $find_descendants = function($parent_uuid) use (&$find_descendants, $database, &$all_links_to_delete) {
      $query = $database->select('menu_link_content_data', 'mlcd');
      $query->join('menu_link_content', 'mlc', 'mlcd.id = mlc.id');
      $query->fields('mlcd', ['id']);
      $query->addField('mlc', 'uuid', 'uuid');
      $query->condition('mlcd.menu_name', 'main');
      $query->condition('mlcd.parent', 'menu_link_content:' . $parent_uuid);
      $results = $query->execute()->fetchAll();
      
      foreach ($results as $row) {
        $all_links_to_delete[] = $row->id;
        // Recursively find children.
        $find_descendants($row->uuid);
      }
    };
    
    // Start with root and find all descendants.
    $all_links_to_delete[] = $old_root_id;
    $find_descendants($old_root_uuid);
    
    // Delete all the old menu links from main menu.
    foreach ($all_links_to_delete as $link_id) {
      $link = $menu_link_storage->load($link_id);
      if ($link && $link->get('menu_name')->value === 'main') {
        try {
          $link->delete();
          $deleted_count++;
        }
        catch (\TypeError $e) {
          // Skip this item if we get a Pathauto TypeError.
          \Drupal::logger('oit_update')->warning('Skipped deleting menu link (ID: @id) due to error: @message', [
            '@id' => $link_id,
            '@message' => $e->getMessage(),
          ]);
        }
        catch (\Exception $e) {
          // Log other exceptions but continue.
          \Drupal::logger('oit_update')->warning('Error deleting menu link (ID: @id): @message', [
            '@id' => $link_id,
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
    
    $stats[$new_menu_id] = $deleted_count;
  }
  
  // Re-enable pathauto if it was enabled.
  if ($pathauto_state !== NULL) {
    \Drupal::configFactory()->getEditable('pathauto.settings')->set('enabled', $pathauto_state)->save();
  }
  
  return $stats;
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
 * Create service category menus using consolidated approach.
 */
function oit_deploy_10002_menu() {
  // Define all menus to replicate.
  $menus = [
    [
      'menu_id' => 'services-conferencing',
      'menu_label' => 'Services - Conferencing Services',
      'menu_description' => 'Menu for conferencing services',
      'root_id' => 3175,
    ],
    [
      'menu_id' => 'services-consulting-prof',
      'menu_label' => 'Services - Consulting & Prof Services',
      'menu_description' => 'Menu for consulting and professional services',
      'root_id' => 3179,
    ],
    [
      'menu_id' => 'services-transfer-store',
      'menu_label' => 'Services - Transfer, Storage, & Infrastructure',
      'menu_description' => 'Menu for file transfer, storage and infrastructure services',
      'root_id' => 3178,
    ],
    [
      'menu_id' => 'services-security',
      'menu_label' => 'Services - IT Security',
      'menu_description' => 'Menu for IT security services',
      'root_id' => 3598,
    ],
    [
      'menu_id' => 'services-identity',
      'menu_label' => 'Services - Identity & Access Management',
      'menu_description' => 'Menu for identity and access management services',
      'root_id' => 9032,
    ],
    [
      'menu_id' => 'services-learning',
      'menu_label' => 'Services - Learning Spaces Technology',
      'menu_description' => 'Menu for learning spaces technology services',
      'root_id' => 3165,
    ],
    [
      'menu_id' => 'services-messaging',
      'menu_label' => 'Services - Messaging & Collaboration',
      'menu_description' => 'Menu for messaging and collaboration services',
      'root_id' => 3542,
    ],
    [
      'menu_id' => 'services-network-internet',
      'menu_label' => 'Services - Network & Internet Services',
      'menu_description' => 'Menu for Network & Internet Services',
      'root_id' => 3174,
    ],
    [
      'menu_id' => 'services-research-computing',
      'menu_label' => 'Services - Research Computing',
      'menu_description' => 'Menu for Research Computing Services',
      'root_id' => 3183,
    ],
    [
      'menu_id' => 'services-software-licensing',
      'menu_label' => 'Services - Software Licensing',
      'menu_description' => 'Menu for Software Licensing Services',
      'root_id' => 42696,
    ],
    [
      'menu_id' => 'services-teaching-learning',
      'menu_label' => 'Services - Teaching & Learning Applications',
      'menu_description' => 'Menu for Teaching & Learning Applications Services',
      'root_id' => 3167,
    ],
    [
      'menu_id' => 'services-voice-coms',
      'menu_label' => 'Services - Voice Communications',
      'menu_description' => 'Menu for Voice Communications Services',
      'root_id' => 8829,
    ],
    [
      'menu_id' => 'services-web-content',
      'menu_label' => 'Services - Web Content & Applications',
      'menu_description' => 'Menu for Web Content & Applications Services',
      'root_id' => 3184,
    ],
    [
      'menu_id' => 'services-business-services',
      'menu_label' => 'Services - Business Services',
      'menu_description' => 'Menu for Web Business Services',
      'root_id' => 3184,
    ],
  ];

  $results = [];
  foreach ($menus as $menu) {
    $count = oit_replicate_menu_tree(
      $menu['menu_id'],
      $menu['menu_label'],
      $menu['menu_description'],
      $menu['root_id']
    );
    $results[] = t('@label: @count items', [
      '@label' => $menu['menu_label'],
      '@count' => $count,
    ]);
  }

  return t('Created @total service category menus: @results', [
    '@total' => count($menus),
    '@results' => implode('; ', $results),
  ]);
}

/**
 * Update nodes' menu links from main menu to new service category menus.
 */
function oit_deploy_10003_update_node_menus() {
  // Define mappings from old root IDs to new menu IDs.
  $menu_mappings = [
    ['old_root_id' => 3175, 'new_menu_id' => 'services-conferencing'],
    ['old_root_id' => 3179, 'new_menu_id' => 'services-consulting-prof'],
    ['old_root_id' => 3178, 'new_menu_id' => 'services-transfer-store'],
    ['old_root_id' => 3598, 'new_menu_id' => 'services-security'],
    ['old_root_id' => 9032, 'new_menu_id' => 'services-identity'],
    ['old_root_id' => 3165, 'new_menu_id' => 'services-learning'],
    ['old_root_id' => 3542, 'new_menu_id' => 'services-messaging'],
    ['old_root_id' => 3174, 'new_menu_id' => 'services-network-internet'],
    ['old_root_id' => 3183, 'new_menu_id' => 'services-research-computing'],
    ['old_root_id' => 42696, 'new_menu_id' => 'services-software-licensing'],
    ['old_root_id' => 3167, 'new_menu_id' => 'services-teaching-learning'],
    ['old_root_id' => 8829, 'new_menu_id' => 'services-voice-coms'],
    ['old_root_id' => 3184, 'new_menu_id' => 'services-web-content'],
  ];
  
  $stats = oit_update_node_menu_links($menu_mappings);
  
  $results = [];
  foreach ($stats as $menu_id => $count) {
    $results[] = "$menu_id: $count links deleted from main menu";
  }
  
  return t('Removed old menu links from main menu. @results', [
    '@results' => implode('; ', $results),
  ]);
}
