<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform.
 */
class WebformHooks {

  /**
   * Implements hook_webform_access_rules_alter().
   */
  #[Hook('webform_access_rules_alter')]
  public function webformAccessRulesAlter(array &$access_rules): void {
    if (isset($access_rules['create'])) {
      $access_rules['create']['roles'] = ['authenticated'];
    }
  }

}
