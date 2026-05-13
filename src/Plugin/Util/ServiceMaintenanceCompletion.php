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
   * Constructs a new ServiceMaintenanceCompletion object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\oit\Plugin\TeamsAlert $teams_alert
   *   The Teams alert service.
   */
  public function __construct(
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    TeamsAlert $teams_alert,
  ) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->teamsAlert = $teams_alert;

    // Service Maintenance Scheduled to Service Maintenance Completed if past
    // end date.
    $this->statusChange('Service Maintenance Scheduled', 'Service Maintenance Completed');

    // Service Maintenance Cancelled to Service Maintenance Completed if past
    // end date.
    $this->statusChange('Service Maintenance Cancelled', 'Service Maintenance Completed');
  }

  /**
   * Update service alert nodes from one status to another when past end date.
   *
   * @param string $from_status
   *   The current status value to look for.
   * @param string $to_status
   *   The status value to set when the end date has passed.
   */
  public function statusChange($from_status, $to_status) {
    $query = $this->connection->select('node__field_service_alert_status', 'sa');
    $query->fields('sa', ['entity_id']);
    $query->condition('sa.field_service_alert_status_value', $from_status);
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
        $node->set('field_service_alert_status', $to_status);
        $node->save();
        $this->teamsAlert->sendMessage("Service maintenance set to '$to_status'. nid: $nid", ['live']);
      }
    }
  }

}
