<?php

namespace Drupal\oit\Plugin;

use Drupal\block_content\Entity\BlockContent;

/**
 * Environment icon to be used on header title.
 *
 * @BlockUuidQuery(
 *   id = "blockuuidquery",
 *   title = @Translation("Block Uuid Query"),
 *   description = @Translation("Query a block for uuid")
 * )
 */
class BlockUuidQuery {
  /**
   * Return icon for environemnt.
   *
   * @var string
   */
  private $bid;

  /**
   * Query block and pull bid via uuid.
   */
  public function __construct($uuid) {
    $query = \Drupal::database()->select('block_content', 'bc');
    $query->fields('bc', ['id']);
    $query->condition('bc.uuid', $uuid);
    $results = $query->execute();
    $results = $results->fetch();
    $this->bid = $results->id;
  }

  /**
   * Return block.
   */
  public function loadBlock() {
    $block = BlockContent::load($this->bid);
    return \Drupal::entityTypeManager()->getViewBuilder('block_content')->view($block);
  }

}
