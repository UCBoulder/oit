<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\key\KeyRepositoryInterface;

/**
 * Plugin to send alert messages into a Microsoft Teams channel via webhook.
 *
 * @TeamsAlert (
 *   id = "teams_alert",
 *   title = @Translation("Teams Alert"),
 *   description = @Translation("Send different alerts into microsoft teams")
 * )
 */
class TeamsAlert {
  /**
   * Stores Teams URL, or NULL until it has been resolved.
   *
   * @var string|null
   */
  private $teamsUrl;

  /**
   * Whether the Teams URL has been resolved.
   *
   * @var bool
   */
  private $teamsUrlResolved = FALSE;

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
   * Constructs a new TeamsAlert object.
   *
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository service.
   * @param \Drupal\encrypt\EncryptServiceInterface $encrypt_service
   *   The encrypt service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $channelFactory
   *   The logger channel factory.
   */
  public function __construct(
    KeyRepositoryInterface $key_repository,
    EncryptServiceInterface $encrypt_service,
    LoggerChannelFactoryInterface $channelFactory,
  ) {
    $this->keyRepository = $key_repository;
    $this->encryptService = $encrypt_service;
    $this->logger = $channelFactory->get('oit');
    $this->env = getenv('PANTHEON_ENVIRONMENT');
  }

  /**
   * Resolves the Teams webhook URL from the encrypted key.
   *
   * Resolved lazily so that constructing this service never requires the
   * 'ms_teams' key or the 'key_encryption' profile to exist.
   *
   * @return string|null
   *   The decrypted webhook URL, or NULL if it cannot be resolved.
   */
  protected function getTeamsUrl() {
    if ($this->teamsUrlResolved) {
      return $this->teamsUrl;
    }
    $this->teamsUrlResolved = TRUE;

    $key = $this->keyRepository->getKey('ms_teams');
    if ($key === NULL) {
      $this->logger->error("Unable to send Teams message: the 'ms_teams' key does not exist.");
      return NULL;
    }
    $encryption_profile = EncryptionProfile::load('key_encryption');
    if ($encryption_profile === NULL) {
      $this->logger->error("Unable to send Teams message: the 'key_encryption' encryption profile does not exist.");
      return NULL;
    }
    $this->teamsUrl = $this->encryptService->decrypt(trim($key->getKeyValue()), $encryption_profile);

    return $this->teamsUrl;
  }

  /**
   * Send a message to the configured Microsoft Teams channel.
   *
   * @param string $message
   *   The message text to send.
   * @param array $environment
   *   List of environments in which to send the alert.
   */
  public function sendMessage(
    $message,
    $environment = ['live', 'dev', 'test', 'local'],
  ) {
    if (!in_array($this->env, $environment)) {
      return;
    }
    $teams_url = $this->getTeamsUrl();
    if (empty($teams_url)) {
      return;
    }
    $this->message = $message;
    $teams_card = json_encode($this->getMessage());
    // Initialize curl handle.
    $ch = curl_init($teams_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $teams_card);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($teams_card),
    ]);
    // Run the whole process.
    $result = curl_exec($ch);
    if ($result !== "1") {
      if ($result !== "") {
        $this->logger->error("Issue sending Teams Message. " . $result);
      }
    }
  }

  /**
   * Build the MS Teams adaptive card payload array.
   *
   * @return array
   *   The adaptive card message array for the Teams webhook.
   */
  public function getMessage() {
    $message = $this->message;
    $env = $this->env;

    $message_array = [
      'type' => 'message',
      'attachments' => [
        [
          'contentType' => 'application/vnd.microsoft.card.adaptive',
          'contentUrl' => NULL,
          'content' => [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.2',
            'body' => [
              [
                'type' => 'TextBlock',
                'size' => 'Large',
                'weight' => 'bolder',
                'text' => "Environment: $env",
                'style' => 'heading',
                'wrap' => 'true',
              ],
              [
                'type' => 'ColumnSet',
                'columns' => [
                  [
                    'type' => 'Column',
                    'items' => [
                      [
                        'type' => 'Image',
                        'style' => 'person',
                        'url' => 'https://avatars.githubusercontent.com/u/105663422?v=4',
                        'altText' => 'Ralphie',
                        'size' => 'small',
                      ],
                    ],
                    'width' => 'auto',
                  ],
                  [
                    'type' => 'Column',
                    'items' => [
                      [
                        'type' => 'TextBlock',
                        'weight' => 'bolder',
                        'text' => 'Ralphie McBuffaloPants',
                        'wrap' => TRUE,
                      ],
                      [
                        'type' => 'TextBlock',
                        'spacing' => 'none',
                        'text' => 'Automation Engineer',
                        'isSubtle' => TRUE,
                        'wrap' => TRUE,
                      ],
                    ],
                    'width' => 'stretch',
                  ],
                ],
              ],
              [
                'type' => 'TextBlock',
                'text' => "$message",
                'wrap' => TRUE,
                'isHtml' => FALSE,
                'markdown' => TRUE,
              ],
            ],
          ],
        ],
      ],
    ];

    return $message_array;
  }

}
