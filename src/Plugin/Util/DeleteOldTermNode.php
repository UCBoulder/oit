<?php

namespace Drupal\oit\Plugin\Util;


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
   * Function to delete old node by term id.
   */
  public function __construct($term_id, $date) {
    $query = \Drupal::database()->select('taxonomy_index', 'ti');
    $query->fields('ti', ['nid']);
    $query->condition('ti.tid', $term_id, 'IN');
    $query->distinct(TRUE);
    $result = $query->execute();
    $fetch = $result->fetchCol();
    $n = 0;
    foreach ($fetch as $nid) {
      if ($n < 20) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
        // $updated_date = $node->getChangedTime();
        $updated_date = $node->getCreatedTime();
        if ($date > $updated_date) {
          $node->delete();
          $teams = \Drupal::service('oit.teamsalert');
          $teams->sendMessage("Deleted old node nid: $nid with term id: $term_id", ['prod']);
        }
        $n++;
      }
    }
  }

}
