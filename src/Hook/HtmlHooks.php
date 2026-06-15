<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\oit\Plugin\EnvironmentIcon;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for HTML preprocessing.
 */
class HtmlHooks {

  /**
   * Constructs a new HtmlHooks object.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\oit\Plugin\EnvironmentIcon $environmentIcon
   *   The OIT environment icon service.
   */
  public function __construct(
    protected ThemeManagerInterface $themeManager,
    protected AccountProxyInterface $currentUser,
    #[Autowire(service: 'oit.environment.icon')]
    protected EnvironmentIcon $environmentIcon,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for html templates.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $theme = $this->themeManager->getActiveTheme()->getName();
    if ($theme == 'gin') {
      $title[0] = $variables['head_title']['name'] ?? '';
      $title[1] = '';
      if (isset($variables['head_title']['title'])) {
        $title[1] = is_string($variables['head_title']['title']) ? $variables['head_title']['title'] : $variables['head_title']['title']->__toString();
      }
      $icon = trim($this->environmentIcon->getEnv());
      $variables['head_title'] = ($icon !== '' ? ($icon . ' ') : '') . $title[1] . ' | ' . $title[0];
    }

    $roles = $this->currentUser->getRoles();
    if (in_array('administrator', $roles)) {
      $env = getenv('PANTHEON_ENVIRONMENT');
      $variables['attributes']['class'][] = "env-$env";
      $variables['#attached']['library'][] = 'oit/oit_env';
    }
  }

}
