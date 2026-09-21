<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oit\Plugin\TeamsAlert;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for entity operations.
 */
class EntityHooks {

  /**
   * Constructs a new EntityHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\oit\Plugin\TeamsAlert $teamsAlert
   *   The OIT Teams alert service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'oit.teamsalert')]
    protected TeamsAlert $teamsAlert,
  ) {}

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

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityType()->getBundleEntityType() == 'node_type' && $entity->getType() == 'webform') {
      if ($webform_id = $entity->get('webform')->getValue()) {
        $webform_id = $webform_id[0]['target_id'];
        $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
        // Report error if webform node is pointing to a deleted webform.
        if ($webform == NULL && $webform_id != 0) {
          $this->teamsAlert->sendMessage('Webform no longer exists but is set on node: ' . $entity->id(), ['live']);
          $this->loggerFactory->get('oit')->error('Webform no longer exists but is set on node: ' . $entity->id());
        }
      }
    }
  }

}
