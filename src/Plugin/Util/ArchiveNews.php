<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oit\Plugin\TeamsAlert;

/**
 * Set archive status on old news.
 *
 * @ArchiveNews(
 *   id = "archive_news",
 *   title = @Translation("Archive News"),
 *   description = @Translation("Set archive status on old news items.")
 * )
 */
class ArchiveNews {

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
   * Construct object.
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
   * Function to archive news after cut off date.
   * $cut_off is a unix timestamp.
   */
  public function archive($cut_off) {
    $query = $this->connection->select('node__field_news_archive', 'na');
    $query->fields('na', ['entity_id']);
    $query->condition('na.field_news_archive_value', 1);
    $result = $query->execute();
    $fetch = $result->fetchCol();
    $updated_nid = '';
    foreach ($fetch as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $updated_date = $node->getChangedTime();
      if ($cut_off > $updated_date) {
        $node->set('field_news_archive', 3);
        $node->set('field_sympa_send', 0);
        $node->save();
        $updated_nid .= "$nid, ";
      }
    }
    if ($updated_nid) {
      $updated_nid = substr($updated_nid, 0, -2);
      $teams = $this->teamsAlert;
      $teams->sendMessage("Archived news nid: $updated_nid", ['prod']);
    }
  }

}
