<?php

namespace Drupal\oit\Plugin\Util;

use Drupal\Core\Database\Connection;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\Core\State\State;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
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
   * The encrypt service.
   *
   * @var \Drupal\encrypt\EncryptServiceInterface
   */
  protected $encryptService;

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
   * Constructs a new LatestAutoBan object.
   *
   * @param \Drupal\encrypt\EncryptServiceInterface $encrypt_service
   *   The encrypt service.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\oit\Plugin\TeamsAlert $teams_alert
   *   The Teams alert service.
   * @param \Drupal\Core\State\State $state
   *   The state service.
   */
  public function __construct(
    EncryptServiceInterface $encrypt_service,
    KeyRepositoryInterface $key_repository,
    Connection $connection,
    TeamsAlert $teams_alert,
    State $state,
  ) {
    $this->encryptService = $encrypt_service;
    $this->keyRepository = $key_repository;
    $this->connection = $connection;
    $this->teamsAlert = $teams_alert;
    $this->state = $state;

    $query = $this->connection->select('advban_ip', 'bi');
    $query->fields('bi', ['iid']);
    $query->orderBy('iid', 'DESC');
    $query->range(0, 1);
    $result = $query->execute()->fetchAll();
    $this->latestBanId = $result[0]->iid;

    $this->lastBanId = $this->state->get('ban_ip_last_id');
  }

  /**
   * Send a Teams message listing newly banned IPs since the last check.
   */
  public function messageLatestIps() {
    $query = $this->connection->select('advban_ip', 'bi');
    $query->fields('bi', ['ip']);
    $query->condition('iid', $this->lastBanId, '>');
    $result = $query->execute()->fetchAll();

    $banned_ips = '';
    foreach ($result as $row) {
      $banned_ips .= "- " . $row->ip . "\n";
      $abuse = $this->abuseApi($row->ip);
      $abuse = json_decode($abuse, TRUE);
      if ($abuse['data']['abuseConfidenceScore'] < 2) {
        $score = $abuse['data']['abuseConfidenceScore'];
        $ip = $abuse['data']['ipAddress'];
        $country = $abuse['data']['countryName'];
        $biq = $this->state->get('ban_ip_questionable');
        $biq = json_decode($biq, TRUE);
        if ($score == NULL || $country == NULL) {
          continue;
        }
        $biq[$ip] = [
          'score' => $score,
          'country' => $country,
        ];
        $biq = json_encode($biq);
        $this->state->set('ban_ip_questionable', $biq);
      }
    }
    $this->teamsAlert->sendMessage("**Latest ip(s) Banned:**\n $banned_ips", ['live']);
    $this->setLastBanId();
  }

  /**
   * Query the AbuseIPDB API for information about an IP address.
   *
   * @param string $ip
   *   The IP address to look up.
   *
   * @return string
   *   The raw JSON response from the AbuseIPDB API.
   *
   * @throws \Exception
   *   Thrown if the cURL request fails.
   */
  public function abuseApi($ip) {
    $key = trim($this->keyRepository->getKey('abuseipdb_crypt')->getKeyValue());

    $encryption_profile = EncryptionProfile::load('key_encryption');
    $abuse_key = $this->encryptService->decrypt($key, $encryption_profile);

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

    // Set request to GET method (default).
    curl_setopt($ch, CURLOPT_HTTPGET, TRUE);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Key: ' . $abuse_key,
      'Accept: application/json',
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Make the call.
    $response = curl_exec($ch);

    // Check for errors.
    if ($response === FALSE) {
      $error = curl_error($ch);
      throw new \Exception("cURL error: $error");
    }

    return $response;
  }

  /**
   * Persist the latest ban ID to state after sending the Teams message.
   */
  public function setLastBanId() {
    $this->state->set('ban_ip_last_id', $this->latestBanId);
  }

}
