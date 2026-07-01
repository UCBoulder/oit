<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for entity operations.
 */
class EntityHooks {

  /**
   * Implements hook_entity_create().
   */
  #[Hook('entity_create')]
  public function entityCreate(EntityInterface $entity): void {
    if ($entity->getEntityType()->getBundleEntityType() == 'node_type' && $entity->getType() == 'webform') {
      // Set access control set to Authenticated.
      $entity->set('field_access_control_2', '475');
    }
  }

}
