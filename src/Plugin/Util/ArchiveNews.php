<?php

namespace Drupal\oit\Plugin\Util;

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
   * Function to archive news after cut off date.
   */
  public function __construct($cut_off) {
    $query = \Drupal::database()->select('node__field_news_archive', 'na');
    $query->fields('na', ['entity_id']);
    $query->condition('na.field_news_archive_value', 1);
    $result = $query->execute();
    $fetch = $result->fetchCol();
    foreach ($fetch as $nid) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $updated_date = $node->getChangedTime();
      if ($cut_off > $updated_date) {
        $node->set('field_news_archive', 3);
        $node->set('field_sympa_send', 0);
        $node->save();
        $teams = \Drupal::service('oit.teamsalert');
        $teams->sendMessage("Archived news nid: $nid", ['prod']);
      }
    }
  }

}
