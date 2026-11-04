<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\oit\Hook\NavigationHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the NavigationHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(NavigationHooks::class)]
#[CoversMethod(NavigationHooks::class, '__construct')]
#[CoversMethod(NavigationHooks::class, 'contentTop')]
class NavigationHooksTest extends DrupalUnitTestCase {

  /**
   * Builds a NavigationHooks instance with mocked dependencies.
   *
   * @param bool $coffee_exists
   *   Whether the coffee module is reported as installed.
   * @param bool $has_permission
   *   Whether the current user has the 'access coffee' permission.
   *
   * @return \Drupal\oit\Hook\NavigationHooks
   *   The configured hooks object.
   */
  protected function buildHooks(bool $coffee_exists, bool $has_permission): NavigationHooks {
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')->with('coffee')->willReturn($coffee_exists);
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->with('access coffee')->willReturn($has_permission);
    $hooks = new NavigationHooks($module_handler, $user);
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

  /**
   * Tests an empty but cacheable item when Coffee is not installed.
   */
  public function testEmptyWhenCoffeeMissing(): void {
    $result = $this->buildHooks(FALSE, TRUE)->contentTop();
    $this->assertSame(['oit_coffee' => ['#cache' => ['contexts' => ['user.permissions']]]], $result);
  }

  /**
   * Tests an empty but cacheable item when the user lacks permission.
   */
  public function testEmptyWithoutPermission(): void {
    $result = $this->buildHooks(TRUE, FALSE)->contentTop();
    $this->assertSame(['oit_coffee' => ['#cache' => ['contexts' => ['user.permissions']]]], $result);
  }

  /**
   * Tests the full render array for a permitted user.
   */
  public function testRenderArrayWhenPermitted(): void {
    $result = $this->buildHooks(TRUE, TRUE)->contentTop();
    $this->assertArrayHasKey('oit_coffee', $result);
    $build = $result['oit_coffee'];

    $this->assertSame('navigation_menu', $build['#theme']);
    $this->assertSame('oit_coffee', $build['#menu_name']);
    $this->assertSame(['user.permissions'], $build['#cache']['contexts']);
    $this->assertSame(['coffee/drupal.coffee', 'oit/navigation_coffee'], $build['#attached']['library']);

    $this->assertCount(1, $build['#items']);
    $item = $build['#items'][0];
    $this->assertSame('Go to', (string) $item['title']);
    $this->assertInstanceOf(Url::class, $item['url']);
    $this->assertSame('<button>', $item['url']->getRouteName());
    $attributes = $item['url']->getOption('attributes');
    $this->assertSame(['oit-coffee-trigger'], $attributes['class']);
    $this->assertSame('button', $attributes['type']);
    $this->assertSame(['pack_id' => 'oit', 'icon_id' => 'coffee-bean'], $item['icon']);
  }

}
