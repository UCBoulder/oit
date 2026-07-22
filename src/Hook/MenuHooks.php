<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;

/**
 * Hook implementations for menu performance tuning and link alterations.
 *
 * The responsive_menu off-canvas and horizontal blocks force the entire menu
 * tree to expand and then run the generic "checkAccess" manipulator on every
 * link. For node links this triggers a full access check per link, which
 * cascades into permissions_by_term database queries and makes page rendering
 * very slow on large menus.
 *
 * Prepending core's "checkNodeAccess" manipulator resolves all node links in a
 * single node-grants query (still honouring permissions_by_term). The generic
 * "checkAccess" manipulator then skips those already-resolved links, while
 * still fully access-checking any non-node links.
 *
 * @see \Drupal\Core\Menu\DefaultMenuLinkTreeManipulators::checkNodeAccess()
 * @see https://github.com/UCBoulder/oit_dingo/issues/803
 */
class MenuHooks {

  /**
   * The bulk node-access manipulator, run before the generic access check.
   */
  protected const NODE_ACCESS_MANIPULATOR = [
    'callable' => 'menu.default_tree_manipulators:checkNodeAccess',
  ];

  /**
   * The SAML login path that carries a destination back to the current page.
   */
  protected const SAML_LOGIN_PATH = '/saml/login';

  /**
   * Constructs a new MenuHooks object.
   *
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   *   The redirect destination helper.
   */
  public function __construct(
    protected RedirectDestinationInterface $redirectDestination,
  ) {}

  /**
   * Prepends the node-access manipulator if it is not already present.
   *
   * @param array $manipulators
   *   The list of menu tree manipulators, passed by reference.
   */
  protected function addNodeAccessManipulator(array &$manipulators): void {
    foreach ($manipulators as $manipulator) {
      if (($manipulator['callable'] ?? NULL) === self::NODE_ACCESS_MANIPULATOR['callable']) {
        return;
      }
    }
    array_unshift($manipulators, self::NODE_ACCESS_MANIPULATOR);
  }

  /**
   * Implements hook_responsive_menu_off_canvas_manipulators_alter().
   */
  #[Hook('responsive_menu_off_canvas_manipulators_alter')]
  public function offCanvasManipulatorsAlter(array &$manipulators): void {
    $this->addNodeAccessManipulator($manipulators);
  }

  /**
   * Implements hook_responsive_menu_horizontal_manipulators_alter().
   */
  #[Hook('responsive_menu_horizontal_manipulators_alter')]
  public function horizontalManipulatorsAlter(array &$manipulators): void {
    $this->addNodeAccessManipulator($manipulators);
  }

  /**
   * Implements hook_preprocess_menu().
   *
   * Adds a "destination" query parameter to the SAML login link so that users
   * are returned to the page they were on instead of the front page. samlauth
   * honours the parameter and rejects external values.
   *
   * @see \Drupal\samlauth\Controller\SamlController::getDestinationUrl()
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    if (($variables['menu_name'] ?? NULL) !== 'account') {
      return;
    }
    $destination = $this->redirectDestination->get();
    // Never send the user back to the login flow itself.
    if (!$destination || str_starts_with($destination, self::SAML_LOGIN_PATH)) {
      return;
    }
    $this->addLoginDestination($variables['items'], $destination);
  }

  /**
   * Implements hook_block_build_alter().
   *
   * The account menu now renders a per-page destination, so its render cache
   * must vary by the current URL.
   */
  #[Hook('block_build_alter')]
  public function blockBuildAlter(array &$build, $block): void {
    if (!str_starts_with($block->getPluginId(), 'system_menu_block:account')) {
      return;
    }
    $build['#cache']['contexts'][] = 'url.path';
    $build['#cache']['contexts'][] = 'url.query_args';
  }

  /**
   * Recursively adds the destination query to any SAML login link.
   *
   * @param array $items
   *   The menu items, passed by reference.
   * @param string $destination
   *   The destination path to return to after login.
   */
  protected function addLoginDestination(array &$items, string $destination): void {
    foreach ($items as &$item) {
      $url = $item['url'] ?? NULL;
      if ($url instanceof Url && $this->isSamlLoginUrl($url)) {
        $query = $url->getOption('query') ?: [];
        $query['destination'] = $destination;
        $url->setOption('query', $query);
      }
      if (!empty($item['below'])) {
        $this->addLoginDestination($item['below'], $destination);
      }
    }
  }

  /**
   * Determines whether a URL points at the SAML login route.
   *
   * @param \Drupal\Core\Url $url
   *   The menu link URL.
   *
   * @return bool
   *   TRUE if the URL is the SAML login link.
   */
  protected function isSamlLoginUrl(Url $url): bool {
    if ($url->isRouted()) {
      return $url->getRouteName() === 'samlauth.saml_controller_login';
    }
    return $url->isExternal() === FALSE && $url->toString() === self::SAML_LOGIN_PATH;
  }

}
