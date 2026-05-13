<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Session\AccountProxyInterface;

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
   * Drupal account object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Return icon for environemnt.
   *
   * @var string
   */
  private $env;

  /**
   * Constructs a new EnvironmentIcon object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user account proxy.
   */
  public function __construct(AccountProxyInterface $account) {
    $this->account = $account;
    // Add icon to title per environment.
    $env = getenv('PANTHEON_ENVIRONMENT');
    $user = $this->account->getRoles();
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
   * Return the environment icon string.
   *
   * @return string
   *   The environment icon emoji string.
   */
  public function getEnv() {
    return $this->env;
  }

}
