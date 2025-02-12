<?php

namespace Drupal\oit\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\servicenow\Plugin\PrincessList;
use Drush\Commands\DrushCommands;
use Drupal\oit\Plugin\TeamsAlert;

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
   * Construct object.
   */
  public function __construct(
    PrincessList $princess_list,
    MessengerInterface $messenger,
    TeamsAlert $teams_alert,
  ) {
    parent::__construct();
    $this->princessList = $princess_list;
    $this->messenger = $messenger;
    $this->teamsAlert = $teams_alert;
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

}
