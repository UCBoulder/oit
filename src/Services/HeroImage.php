<?php

namespace Drupal\oit\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves the file ID of a node's hero media image.
 */
class HeroImage {

  /**
   * Constructs a new HeroImage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Gets the file ID of a node's hero media image, or NULL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to inspect.
   *
   * @return int|null
   *   The file entity ID, or NULL when the node has no hero image.
   */
  public function getFileId(NodeInterface $node): ?int {
    if (!$node->hasField('field_media_hero_image') || $node->get('field_media_hero_image')->isEmpty()) {
      return NULL;
    }
    $media_id = $node->get('field_media_hero_image')->getValue()[0]['target_id'];
    $media = $this->entityTypeManager->getStorage('media')->load($media_id);
    if (!$media || $media->get('field_media_image_2')->isEmpty()) {
      return NULL;
    }
    return (int) $media->get('field_media_image_2')->getValue()[0]['target_id'];
  }

}
