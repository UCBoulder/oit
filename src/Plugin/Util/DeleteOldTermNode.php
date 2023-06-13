<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oit\Plugin\TeamsAlert;

/**
 * Provides ability to delete old nodes by term - prev used on security notices.
 *
 * @DeleteOldTermNode(
 *   id = "delete_old_term_node",
 *   title = @Translation("Delete Old term node"),
 *   description = @Translation("Delete old nodes by term from certain date")
 * )
 */
class DeleteOldTermNode {

  /**
   * Run Database query.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Send teams alert.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  protected $teamsAlert;

  /**
   * Constructs a new DeleteOldTermNode object.
   */
  public function __construct(
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    TeamsAlert $teams_alert
  ) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->teamsAlert = $teams_alert;
  }

  /**
   * Function to delete old node by term id.
   */
  public function update($term_id, $date) {
    $query = $this->connection->select('taxonomy_index', 'ti');
    $query->fields('ti', ['nid']);
    $query->condition('ti.tid', $term_id, 'IN');
    $query->distinct(TRUE);
    $result = $query->execute();
    $fetch = $result->fetchCol();
    $updated_nid = '';
    foreach ($fetch as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      // $updated_date = $node->getChangedTime();
      $updated_date = $node->getCreatedTime();
      if ($date > $updated_date) {
        $node->delete();
        $updated_nid .= "$nid, ";
      }
    }
    if ($updated_nid) {
      $updated_nid = substr($updated_nid, 0, -2);
      $teams = $this->teamsAlert;
      $teams->sendMessage("Deleted old node nid: $updated_nid with term id: $term_id \n <b>service:</b> oit.dotn", ['prod']);
    }
  }

}
