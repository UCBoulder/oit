<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for responsive_menu performance tuning.
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

}
