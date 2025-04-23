<?php

namespace Drupal\oit\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\servicenow\Plugin\PrincessList;
use Drush\Commands\DrushCommands;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\Core\Database\Connection;
use Drupal\oit\Plugin\Util\UserClean;

/**
 * Various utility commands for OIT.
 */
class OitCommands extends DrushCommands {

  /**
   * Princess List.
   *
   * @var \Drupal\servicenow\Plugin\PrincessList
   */
  protected $princessList;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Teams Alert.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  protected $teamsAlert;

  /**
   * The Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * User Clean.
   *
   * @var \Drupal\oit\Plugin\UserClean
   */
  protected $userClean;

  /**
   * Construct object.
   */
  public function __construct(
    PrincessList $princess_list,
    MessengerInterface $messenger,
    TeamsAlert $teams_alert,
    Connection $database,
    UserClean $user_clean
  ) {
    parent::__construct();
    $this->princessList = $princess_list;
    $this->messenger = $messenger;
    $this->teamsAlert = $teams_alert;
    $this->database = $database;
    $this->userClean = $user_clean;
  }

  /**
   * Rebuild Princess List.
   *
   * @command oit:reload-princess
   * @aliases oit:rp
   */
  public function reloadPrincess() {
    $this->princessList->reload();
    $this->messenger->addMessage('Princess List reloaded.');
  }

  /**
   * Load Princess List.
   *
   * @param bool $incremental
   *   Set to 1 or 0 to incrementally load.
   *
   * @usage oit:lp 1
   *   Loads users into princess list incrementally.
   *
   * @command oit:load-princess
   * @aliases oit:lp
   */
  public function loadPrincess($incremental = 0) {
    $this->princessList->cron($incremental);
    $this->messenger->addMessage('Princess List Loaded.');
  }

  /**
   * Send Teams Alert.
   *
   * @command oit:send-teams-alert
   * @aliases oit:sta
   */
  public function sendTeamsAlert($userMessage) {
    $teams = $this->teamsAlert;
    $teams->sendMessage($userMessage);
    $this->messenger->addMessage('Teams Alert Sent.');
  }

  /**
   * Clean banned ip's.
   *
   * @command oit:ban-ip-clean
   * @aliases oit:bic
   */
  public function bannedIpClean($keep = 300) {
    $query = $this->database->select('ban_ip', 'b');
    $query->fields('b', ['iid']);
    $query->orderBy('iid', 'ASC');
    $result = $query->execute()->fetchAll();

    $count = count($result);

    if ($count <= $keep) {
      $this->messenger->addMessage('Banned IP\'s are less than the keep value.');
      return;
    }

    $how_many_to_remove = $count - $keep;

    $result_remove = [];
    $i = 0;
    foreach ($result as $row) {
      if ($i <= $how_many_to_remove) {
        $result_remove[] = $row->iid;
      }
      $i++;
    }

    foreach ($result_remove as $row) {
      $this->database->delete('ban_ip')
        ->condition('iid', $row)
        ->execute();
    }
    $this->messenger->addMessage('Banned IP\'s cleaned.');
  }

  /**
   * Clean users that haven't accessed the site in over a year.
   *
   * @command oit:clean-users
   * @aliases oit:cu
   */
  public function cleanUsers($limit = 0) {
    $this->userClean->removeUsers($limit);
  }

}
