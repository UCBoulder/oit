<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\Core\State\State;
use Drupal\key\KeyRepositoryInterface;

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
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

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
    KeyRepositoryInterface $key_repository,
    Connection $connection,
    TeamsAlert $teams_alert,
    State $state,
  ) {
    $this->keyRepository = $key_repository;
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
      $abuse = $this->abuseApi($row->ip);
      $abuse = json_decode($abuse, TRUE);
      if ($abuse['data']['abuseConfidenceScore'] < 10) {
        $score = $abuse['data']['abuseConfidenceScore'];
        $ip = $abuse['data']['ipAddress'];
        $country = $abuse['data']['countryName'];
        $biq = $this->state->get('ban_ip_questionable');
        $biq = json_decode($biq, TRUE);
        $biq[$ip] = [
          'score' => $score,
          'country' => $country,
        ];
        $biq = json_encode($biq);
        $this->state->set('ban_ip_questionable', $biq);
      }
    }
    $this->teamsAlert->sendMessage("**Latest ip(s) Banned:**\n $banned_ips");
    $this->setLastBanId();
  }

  /**
   * Curl abuseipdb api.
   */
  public function abuseApi($ip) {
    $abuse_key = $this->keyRepository->getKey('abuseipdb')->getKeyValue();
    // Use cURL to get a new access token and refresh token.
    $ch = curl_init();

    // Define base URL with query parameters.
    $params = [
      'ipAddress' => $ip,
      'maxAgeInDays' => 90,
      'verbose' => '',
    ];
    $url = 'https://api.abuseipdb.com/api/v2/check?' . http_build_query($params);

    curl_setopt($ch, CURLOPT_URL, $url);

    // Set request to GET method (default)
    curl_setopt($ch, CURLOPT_HTTPGET, TRUE);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Key: ' . $abuse_key,
      'Accept: application/json',
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    // Make the call.
    return curl_exec($ch);
  }

  /**
   * Set last id after teams message.
   */
  public function setLastBanId() {
    $this->state->set('ban_ip_last_id', $this->latestBanId);
  }

}
