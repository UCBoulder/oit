<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for the core Navigation sidebar.
 *
 * Core Navigation replaces the Toolbar, so the Coffee module's "Go to" launcher
 * (added through hook_toolbar()) no longer appears. This adds an equivalent
 * button at the top of the Navigation sidebar.
 */
class NavigationHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new NavigationHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_navigation_content_top().
   *
   * Adds a "Go to" button that opens the Coffee pop-up.
   *
   * @return array
   *   Render arrays keyed by item name.
   */
  #[Hook('navigation_content_top')]
  public function contentTop(): array {
    $cache = ['contexts' => ['user.permissions']];
    if (!$this->moduleHandler->moduleExists('coffee') || !$this->currentUser->hasPermission('access coffee')) {
      return ['oit_coffee' => ['#cache' => $cache]];
    }

    return [
      'oit_coffee' => [
        '#theme' => 'navigation_menu',
        '#menu_name' => 'oit_coffee',
        '#items' => [
          [
            'title' => $this->t('Go to'),
            'url' => Url::fromRoute('<button>', [], [
              'attributes' => [
                'class' => ['oit-coffee-trigger'],
                'title' => $this->t('Use alt+d to start Coffee and search for a page to go to'),
                'type' => 'button',
              ],
            ]),
            'icon' => [
              'pack_id' => 'oit',
              'icon_id' => 'coffee-bean',
            ],
          ],
        ],
        '#weight' => -100,
        '#attached' => [
          'library' => [
            'coffee/drupal.coffee',
            'oit/navigation_coffee',
          ],
        ],
        '#cache' => $cache,
      ],
    ];
  }

}
