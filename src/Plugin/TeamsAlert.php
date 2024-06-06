<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\key\KeyRepositoryInterface;

/**
 * Environment icon to be used on header title.
 *
 * @TeamsAlert (
 *   id = "teams_alert",
 *   title = @Translation("Teams Alert"),
 *   description = @Translation("Send different alerts into microsoft teams")
 * )
 */
class TeamsAlert {
  /**
   * Stores Teams URL.
   *
   * @var string
   */
  private $teamsUrl;

  /**
   * Message to send.
   *
   * @var string
   */
  private $message;

  /**
   * Set Environment.
   *
   * @var string
   */
  private $env;

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
   * The Teams logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Sets up to send message to Teams.
   */
  public function __construct(
    KeyRepositoryInterface $key_repository,
    EncryptServiceInterface $encrypt_service,
    LoggerChannelFactoryInterface $channelFactory,
  ) {
    $this->keyRepository = $key_repository;
    $this->encryptService = $encrypt_service;
    $this->logger = $channelFactory->get('oit');
    $key_encrypted = trim($this->keyRepository->getKey('ms_teams')->getKeyValue());
    $encryption_profile = EncryptionProfile::load('key_encryption');
    $this->teamsUrl = $this->encryptService->decrypt($key_encrypted, $encryption_profile);
    $this->env = getenv('PANTHEON_ENVIRONMENT');
  }

  /**
   * Send message.
   */
  public function sendMessage(
    $message,
    $environment = ['prod', 'dev', 'test', 'local'],
  ) {
    if (!in_array($this->env, $environment)) {
      return;
    }
    $this->message = $message;
    $teams_card = json_encode($this->getMessage());
    // Initialize curl handle.
    $ch = curl_init($this->teamsUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $teams_card);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($teams_card),
    ]);
    // Run the whole process.
    $result = curl_exec($ch);
    if ($result !== "1") {
      $this->logger->error("Issue sending Teams Message. " . $result);
    }
    // Close cURL handler.
    curl_close($ch);
  }

  /**
   * Setup MS Teams card.
   */
  public function getMessage() {
    return [
      "@type" => "MessageCard",
      "@context" => "http://schema.org/extensions",
      "summary" => "Forge Card",
      "themeColor" => '#2E96FF',
      "title" => "Message from dingo",
      "sections" => [
        [
          "activityTitle" => "Environment: " . $this->env,
          "activitySubtitle" => "",
          "activityImage" => "",
          "text" => $this->message,
        ],
      ],
    ];
  }

}
