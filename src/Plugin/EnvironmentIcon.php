<?php

namespace Drupal\oit\Plugin;

/**
 * Environment icon to be used on header title.
 *
 * @EnvironmentIcon(
 *   id = "environmenticon",
 *   title = @Translation("Environment Icon"),
 *   description = @Translation("For pulling in an environment icon")
 * )
 */
class EnvironmentIcon {
  /**
   * Return icon for environemnt.
   *
   * @var string
   */
  private $env;

  /**
   * Check environment and give icon accordingly.
   */
  public function __construct() {
    // Add icon to title per environment.
    $env = getenv('PANTHEON_ENVIRONMENT');
    $user = \Drupal::currentUser()->getRoles();
    $env_icon = '';
    if ($env == 'local' || $env == 'LANDO') {
      $env_icon = '✅🐕 ';
    }
    if ($env == 'dev') {
      $env_icon = '🟢🐕 ';
    }
    if ($env == 'test') {
      $env_icon = '🟡🐕 ';
    }
    if (($env == 'live') && (in_array('administrator', $user))) {
      $env_icon = '🔴🐕 ';
    }
    $this->env = $env_icon;
  }

  /**
   * Return icon.
   */
  public function getEnv() {
    return $this->env;
  }

}
