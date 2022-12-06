<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\oit\Plugin\TeamsAlert;

/**
 * Set Serv Maint Completed when past end date.
 *
 * @ServiceMaintenanceCompletion(
 *   id = "service_maintenance_completion",
 *   title = @Translation("Service Maintenance Completion"),
 *   description = @Translation("Set service maint complete when past now")
 * )
 */
class ServiceMaintenanceCompletion {

  /**
   * Function to set to Service maintenance completed once past end date.
   */
  public function __construct() {
    $query = \Drupal::database()->select('node__field_service_alert_status', 'sa');
    $query->fields('sa', ['entity_id']);
    $query->condition('sa.field_service_alert_status_value', 'Service Maintenance Scheduled');
    $result = $query->execute();
    $fetch = $result->fetchCol();
    foreach ($fetch as $nid) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $end_date = $node->get('field_service_alert_iss_resolve1')->getValue();
      $end_timestamp = strtotime($end_date[0]['value']);
      $now = time();
      // If the end date is past now, set to service maintenance completed.
      if ($now > $end_timestamp) {
        $node->set('field_sympa_send', 0);
        $node->set('field_service_alert_status', 'Service Maintenance Completed');
        $node->save();
        $teams = new TeamsAlert();
        $teams->sendMessage("Service maintenance set to completed. nid: $nid", ['prod']);
      }
    }
  }

}
