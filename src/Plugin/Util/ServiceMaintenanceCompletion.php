<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oit\Plugin\TeamsAlert;

/**
 * Set Serv Maint Completed when past end date.
 *
 * @smc(
 *   id = "service_maintenance_completion",
 *   title = @Translation("Service Maintenance Completion"),
 *   description = @Translation("Set service maint complete when past now")
 * )
 */
class ServiceMaintenanceCompletion {

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
   * Function to set to Service maintenance completed once past end date.
   */
  public function __construct(
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    TeamsAlert $teams_alert,
  ) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->teamsAlert = $teams_alert;
    $query = $this->connection->select('node__field_service_alert_status', 'sa');
    $query->fields('sa', ['entity_id']);
    $query->condition('sa.field_service_alert_status_value', 'Service Maintenance Scheduled');
    $result = $query->execute();
    $fetch = $result->fetchCol();
    foreach ($fetch as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $end_date = $node->get('field_service_alert_iss_resolve1')->getValue();
      $end_timestamp = strtotime($end_date[0]['value']);
      $now = time();
      // If the end date is past now, set to service maintenance completed.
      if ($now > $end_timestamp) {
        $node->set('field_sympa_send', 0);
        $node->set('field_service_alert_status', 'Service Maintenance Completed');
        $node->save();
        $this->teamsAlert->sendMessage("Service maintenance set to completed. nid: $nid", ['prod']);
      }
    }
  }

}
