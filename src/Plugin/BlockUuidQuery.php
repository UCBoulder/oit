<?php

namespace Drupal\oit\Plugin;

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
   * @var string|null
   */
  private $bid;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BlockUuidQuery object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Query block and pull bid via uuid.
   *
   * @param string $uuid
   *   The UUID of the block content entity.
   *
   * @return void
   *   Nothing is returned; the block ID is stored on the instance.
   */
  public function getBidByUuid($uuid) {
    $query = $this->connection->select('block_content', 'bc');
    $query->fields('bc', ['id']);
    $query->condition('bc.uuid', $uuid);
    $results = $query->execute();
    $row = $results->fetch();
    $this->bid = $row->id ?? NULL;
  }

  /**
   * Load and return a rendered block entity.
   *
   * @return array<string, mixed>
   *   Render array for the block content entity.
   */
  public function loadBlock() {
    $block = $this->entityTypeManager->getStorage('block_content')->load($this->bid);
    return $this->entityTypeManager->getViewBuilder('block_content')->view($block);
  }

}
