<?php

namespace Drupal\oit\Hook;

use Drupal\block\BlockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for block visibility on node pages.
 */
class BlockHooks {

  /**
   * The block plugin ID of the "Unpublished Node" banner.
   */
  protected const UNPUBLISHED_NODE_PLUGIN = 'block_content:b64c8b8f-40f5-492b-9cd9-4261baee0cfd';

  /**
   * Constructs a new BlockHooks object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_block_access().
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block being checked.
   * @param string $operation
   *   The operation to be performed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  #[Hook('block_access')]
  public function blockAccess(BlockInterface $block, string $operation, AccountInterface $account): AccessResultInterface {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return AccessResult::neutral();
    }
    // Only show the "Unpublished Node" banner on unpublished nodes.
    if ($block->getPluginId() == self::UNPUBLISHED_NODE_PLUGIN && $node->isPublished()) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    // Hide the sidebar main navigation on basic pages that have
    // "Show child links" unchecked.
    if ($block->id() == 'mainnavigation' && $node->bundle() == 'page') {
      if (!$node->get('field_show_child_links')->value) {
        return AccessResult::forbidden()->addCacheableDependency($node);
      }
    }
    return AccessResult::neutral();
  }

}
