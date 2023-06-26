<?php

namespace Drupal\oit\Plugin;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Load block by uuid.
 *
 * @BlockUuidQuery(
 *   id = "blockuuidquery",
 *   title = @Translation("Block Uuid Query"),
 *   description = @Translation("Query a block for uuid")
 * )
 */
class BlockUuidQuery {

  /**
   * Run Database query.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Return block id.
   *
   * @var string
   */
  private $bid;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct object.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Query block and pull bid via uuid.
   */
  public function getBidByUuid($uuid) {
    $query = $this->connection->select('block_content', 'bc');
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
    return $this->entityTypeManager->getViewBuilder('block_content')->view($block);
  }

}
