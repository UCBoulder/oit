<?php

namespace Drupal\oit\Plugin;

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
   * Sets up to send message to Teams.
   */
  public function __construct() {
    $this->teamsUrl = trim(\Drupal::service('key.repository')->getKey('ms_teams')->getKeyValue());
    $this->env = getenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * Send message.
   */
  public function sendMessage(
    $message,
    $environment = ['prod', 'dev', 'test', 'local', 'LANDO']
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
      \Drupal::logger('my_module')->error("Issue sending Teams Message. " . $result);
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
