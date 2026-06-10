<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oit\Plugin\TeamsAlert;

/**
 * Removes user accounts that have not logged in for over a year.
 *
 * @UserClean(
 *   id = "user_clean",
 *   title = @Translation("User Clean"),
 *   description = @Translation("Remove inactive user accounts")
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
   * Constructs a new UserClean object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\oit\Plugin\TeamsAlert $teams_alert
   *   The Teams alert service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    TeamsAlert $teams_alert,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->teamsAlert = $teams_alert;
  }

  /**
   * Remove users that have not logged in for over a year.
   *
   * @param int $limit
   *   Maximum number of users to delete (0 = no limit).
   */
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
          // Do not remove test users or administrators.
          $user_roles = $user->getRoles();
          if (strpos($user->getAccountName(), 'thereal') !== 0 && !in_array('administrator', $user_roles, TRUE)) {
            $user->delete();
          }
        }
      }

      $this->teamsAlert->sendMessage("$count Users that haven't accessed the site in over a year removed.", ['live']);
    }

  }

}
