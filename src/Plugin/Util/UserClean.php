<?php

namespace Drupal\oit\Plugin\Util;

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
class UserClean {

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
    EntityTypeManagerInterface $entity_type_manager,
    TeamsAlert $teams_alert,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->teamsAlert = $teams_alert;
  }

  public function removeUsers($limit = 0) {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $query = $user_storage->getQuery();
    $query->condition('status', 1);
    $query->condition('access', strtotime('-1 year'), '<');
    $query->accessCheck(TRUE);
    if ($limit !== 0) {
      $query->range(0, $limit);
    }
    $results = $query->execute();

    // Delete users that have not logged in for over a year.
    if (!empty($results)) {
      $count = count($results);

      foreach ($results as $uid) {
        $user = $user_storage->load($uid);
        if ($user) {
          // Do not remove test users.
          if (strpos($user->getAccountName(), 'thereal') !== 0) {
            $user->delete();
          }
        }
      }

      $this->teamsAlert->sendMessage("$count Users that haven't accessed the site in over a year removed.", ['live']);
    }

  }

}
