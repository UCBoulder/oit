<?php

namespace Drupal\oit\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\DrushCommands;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\oit\Plugin\TopPages;
use Drupal\Core\Database\Connection;
use Drupal\oit\Plugin\Util\UserClean;

/**
 * Various utility commands for OIT.
 */
class OitCommands extends DrushCommands {

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
   * @var \Drupal\oit\Plugin\Util\UserClean
   */
  protected $userClean;

  /**
   * Top Pages.
   *
   * @var \Drupal\oit\Plugin\TopPages
   */
  protected $topPages;

  /**
   * Construct object.
   */
  public function __construct(
    MessengerInterface $messenger,
    TeamsAlert $teams_alert,
    Connection $database,
    UserClean $user_clean,
    TopPages $top_pages,
  ) {
    parent::__construct();
    $this->messenger = $messenger;
    $this->teamsAlert = $teams_alert;
    $this->database = $database;
    $this->userClean = $user_clean;
    $this->topPages = $top_pages;
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

  /**
   * Run top service pages report.
   *
   * @command oit:top-service-pages
   * @aliases oit:tsp
   */
  public function topServicePages() {
    $this->topPages->getTopPages();
  }

  /**
   * Run top tutorial pages report.
   *
   * @command oit:top-tutorial-pages
   * @aliases oit:ttp
   */
  public function topTutorialPages() {
    $this->topPages->getTopTutorials();
  }

}
