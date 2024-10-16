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
    $environment = ['live', 'dev', 'test', 'local'],
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
    $message = $this->message;
    $env = $this->env;

    $message_array = [
      'type' => 'message',
      'attachments' => [
        [
          'contentType' => 'application/vnd.microsoft.card.adaptive',
          'contentUrl' => NULL,
          'content' => [
            '$schema' => 'http =>//adaptivecards.io/schemas/adaptive-card.json',
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
              ],
            ],
          ],
        ],
      ],
    ];

    return $message_array;
  }

}
