<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\block\BlockInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\BlockHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the BlockHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(BlockHooks::class)]
#[CoversMethod(BlockHooks::class, 'blockAccess')]
class BlockHooksTest extends DrupalUnitTestCase {

  /**
   * The "Unpublished Node" banner block plugin ID.
   */
  protected const UNPUBLISHED_NODE_PLUGIN = 'block_content:b64c8b8f-40f5-492b-9cd9-4261baee0cfd';

  /**
   * The current user mock.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->account = $this->createMock(AccountInterface::class);
  }

  /**
   * Tests block access is registered only as an object-oriented hook.
   */
  public function testBlockAccessHookRegistration(): void {
    require_once __DIR__ . '/../../../../oit.module';

    $method = new \ReflectionMethod(BlockHooks::class, 'blockAccess');
    $attributes = $method->getAttributes(Hook::class);
    $this->assertCount(1, $attributes);
    $this->assertSame('block_access', $attributes[0]->newInstance()->hook);
    $this->assertFalse(function_exists('oit_block_access'));
  }

  /**
   * Tests the unpublished banner is hidden on published nodes.
   */
  public function testUnpublishedBannerHiddenOnPublishedNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCacheTags')->willReturn(['node:1']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $result = $this->access($this->block(self::UNPUBLISHED_NODE_PLUGIN), $node);

    $this->assertTrue($result->isForbidden());
    $this->assertContains('node:1', $result->getCacheTags());
  }

  /**
   * Tests the unpublished banner is left alone on unpublished nodes.
   */
  public function testUnpublishedBannerNeutralOnUnpublishedNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('isPublished')->willReturn(FALSE);

    $result = $this->access($this->block(self::UNPUBLISHED_NODE_PLUGIN), $node);

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests the main navigation is hidden on pages without child links.
   */
  public function testMainNavigationHiddenWithoutChildLinks(): void {
    $result = $this->access($this->block('menu_block:main', 'mainnavigation'), $this->page(FALSE));

    $this->assertTrue($result->isForbidden());
    $this->assertContains('node:2', $result->getCacheTags());
  }

  /**
   * Tests the main navigation is hidden when the child links field is empty.
   */
  public function testMainNavigationHiddenWithEmptyChildLinks(): void {
    $result = $this->access($this->block('menu_block:main', 'mainnavigation'), $this->page(NULL));

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Tests the main navigation is left alone on pages with child links.
   */
  public function testMainNavigationNeutralWithChildLinks(): void {
    $result = $this->access($this->block('menu_block:main', 'mainnavigation'), $this->page(TRUE));

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests the main navigation is left alone on other bundles.
   */
  public function testMainNavigationNeutralOnOtherBundles(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('news');
    $node->expects($this->never())->method('get');

    $result = $this->access($this->block('menu_block:main', 'mainnavigation'), $node);

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests both blocks are left alone when the route has no node object.
   */
  public function testNeutralWithoutNode(): void {
    $this->assertTrue($this->access($this->block(self::UNPUBLISHED_NODE_PLUGIN), NULL)->isNeutral());
    $this->assertTrue($this->access($this->block('menu_block:main', 'mainnavigation'), '5')->isNeutral());
  }

  /**
   * Runs the block access hook with the given route node parameter.
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block being checked.
   * @param mixed $node
   *   The route's node parameter.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function access(BlockInterface $block, mixed $node) {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('node')->willReturn($node);
    return (new BlockHooks($route_match))->blockAccess($block, 'view', $this->account);
  }

  /**
   * Creates a block mock.
   *
   * @param string $plugin_id
   *   The block plugin ID.
   * @param string $id
   *   The block config ID.
   *
   * @return \Drupal\block\BlockInterface
   *   The block mock.
   */
  protected function block(string $plugin_id, string $id = 'unpublishednode'): BlockInterface {
    $block = $this->createMock(BlockInterface::class);
    $block->method('getPluginId')->willReturn($plugin_id);
    $block->method('id')->willReturn($id);
    return $block;
  }

  /**
   * Creates a basic page node mock.
   *
   * @param bool|null $show_child_links
   *   The value of the show child links field.
   *
   * @return \Drupal\node\NodeInterface
   *   The node mock.
   */
  protected function page(?bool $show_child_links): NodeInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($show_child_links);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('page');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCacheTags')->willReturn(['node:2']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    $node->method('get')->with('field_show_child_links')->willReturn($field);
    return $node;
  }

}
