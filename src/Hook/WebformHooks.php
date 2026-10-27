<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionStorageInterface;

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

  /**
   * Implements hook_ENTITY_TYPE_create() for webform entities.
   */
  #[Hook('webform_create')]
  public function webformCreate(WebformInterface $webform): void {
    // Default new webforms to purge all submissions after 30 days.
    if ($webform->getSetting('purge') === WebformSubmissionStorageInterface::PURGE_NONE) {
      $webform->setSetting('purge', WebformSubmissionStorageInterface::PURGE_ALL);
      $webform->setSetting('purge_days', 30);
    }
  }

}
