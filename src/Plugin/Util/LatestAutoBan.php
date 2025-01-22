<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\Core\State\State;

/**
 * Set archive status on old news.
 *
 * @ArchiveNews(
 *   id = "archive_news",
 *   title = @Translation("Archive News"),
 *   description = @Translation("Set archive status on old news items.")
 * )
 */
class LatestAutoBan {

  /**
   * Run Database query.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Send teams alert.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  protected $teamsAlert;

  /**
   * State.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * Get the latest banned id.
   *
   * @var int
   */
  public $latestBanId;

  /**
   * Get the last banned id.
   *
   * @var int
   */
  public $lastBanId;

  /**
   * Construct object.
   */
  public function __construct(
    Connection $connection,
    TeamsAlert $teams_alert,
    State $state,
  ) {
    $this->connection = $connection;
    $this->teamsAlert = $teams_alert;
    $this->state = $state;

    $query = $this->connection->select('ban_ip', 'bi');
    $query->fields('bi', ['iid']);
    $query->orderBy('iid', 'DESC');
    $query->range(0, 1);
    $result = $query->execute()->fetchAll();
    $this->latestBanId = $result[0]->iid;

    $this->lastBanId = $this->state->get('ban_ip_last_id');
  }

  /**
   * Send message to teams listing new banned ip's..
   */
  public function messageLatestIps() {
    $query = $this->connection->select('ban_ip', 'bi');
    $query->fields('bi', ['ip']);
    $query->condition('iid', $this->lastBanId, '>');
    $result = $query->execute()->fetchAll();

    $banned_ips = '';
    foreach ($result as $row) {
      $banned_ips .= "- " . $row->ip . "\n";
    }
    $this->teamsAlert->sendMessage("**Latest ip(s) Banned:**\n $banned_ips");
    $this->setLastBanId();
  }

  /**
   * Set last id after teams message.
   */
  public function setLastBanId() {
    $this->state->set('ban_ip_last_id', $this->latestBanId);
  }

}
