<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\oit\Hook\MenuHooks;
use Drupal\oit\Plugin\Domain;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the MenuHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(MenuHooks::class)]
#[CoversMethod(MenuHooks::class, '__construct')]
#[CoversMethod(MenuHooks::class, 'offCanvasTreeAlter')]
#[CoversMethod(MenuHooks::class, 'socialLink')]
#[CoversMethod(MenuHooks::class, 'addNodeAccessManipulator')]
#[CoversMethod(MenuHooks::class, 'offCanvasManipulatorsAlter')]
#[CoversMethod(MenuHooks::class, 'horizontalManipulatorsAlter')]
#[CoversMethod(MenuHooks::class, 'preprocessMenu')]
#[CoversMethod(MenuHooks::class, 'blockBuildAlter')]
#[CoversMethod(MenuHooks::class, 'addLoginDestination')]
#[CoversMethod(MenuHooks::class, 'isSamlLoginUrl')]
class MenuHooksTest extends DrupalUnitTestCase {

  /**
   * Builds a MenuHooks instance with mocked dependencies.
   *
   * @param string $domain
   *   The domain identifier the mocked Domain service should report.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface|null $redirectDestination
   *   An optional redirect destination mock; one is created when omitted.
   *
   * @return \Drupal\oit\Hook\MenuHooks
   *   The configured hooks object.
   */
  protected function buildHooks(string $domain = 'oit', ?RedirectDestinationInterface $redirectDestination = NULL): MenuHooks {
    $domain_mock = $this->createMock(Domain::class);
    $domain_mock->method('getDomain')->willReturn($domain);

    return new MenuHooks(
      $redirectDestination ?? $this->createMock(RedirectDestinationInterface::class),
      $domain_mock,
    );
  }

  /**
   * Builds a mocked Url with all branches of isSamlLoginUrl() stubbed.
   *
   * @param bool $routed
   *   Whether the URL is routed.
   * @param string|null $route_name
   *   The route name to report when routed.
   * @param bool $external
   *   Whether the URL is external, when unrouted.
   * @param string $as_string
   *   The string representation, when unrouted.
   *
   * @return \Drupal\Core\Url|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked URL.
   */
  protected function mockUrl(bool $routed, ?string $route_name, bool $external, string $as_string): Url {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn($routed);
    $url->method('getRouteName')->willReturn($route_name);
    $url->method('isExternal')->willReturn($external);
    $url->method('toString')->willReturn($as_string);

    return $url;
  }

  /* --------------------------------------------------------------------
   * offCanvasTreeAlter
   * ----------------------------------------------------------------- */

  /**
   * Tests the off-canvas suffix contains the social, feedback and close links.
   */
  public function testOffCanvasTreeAlterOnOitDomain(): void {
    $hooks = $this->buildHooks('oit');
    $rendered_tree = [];
    $hooks->offCanvasTreeAlter($rendered_tree);

    $this->assertArrayHasKey('#suffix', $rendered_tree);
    $suffix = $rendered_tree['#suffix'];

    $social_links = (new \ReflectionClassConstant(MenuHooks::class, 'SOCIAL_LINKS'))->getValue();
    $this->assertCount(4, $social_links);
    foreach ($social_links as $url) {
      $this->assertStringContainsString($url, $suffix);
    }
    $this->assertStringContainsString('mailto:oithelp@colorado.edu', $suffix);
    $this->assertStringContainsString("href='#mm-0'", $suffix);
  }

  /**
   * Tests no suffix is added when the domain is not oit.
   */
  public function testOffCanvasTreeAlterSkipsNonOitDomain(): void {
    $hooks = $this->buildHooks('na');
    $rendered_tree = [];
    $hooks->offCanvasTreeAlter($rendered_tree);

    $this->assertArrayNotHasKey('#suffix', $rendered_tree);
    $this->assertSame([], $rendered_tree);
  }

  /* --------------------------------------------------------------------
   * offCanvasManipulatorsAlter / horizontalManipulatorsAlter
   * ----------------------------------------------------------------- */

  /**
   * Tests both entry points prepend the node access manipulator identically.
   */
  #[DataProvider('manipulatorProvider')]
  public function testAddNodeAccessManipulatorBothEntryPoints(array $input, array $expected): void {
    $hooks = $this->buildHooks();

    $off_canvas = $input;
    $hooks->offCanvasManipulatorsAlter($off_canvas);
    $this->assertSame($expected, $off_canvas);

    $horizontal = $input;
    $hooks->horizontalManipulatorsAlter($horizontal);
    $this->assertSame($expected, $horizontal);
  }

  /**
   * Data provider for addNodeAccessManipulator cases.
   *
   * @return array
   *   Test cases.
   */
  public static function manipulatorProvider(): array {
    $check_node_access = ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'];
    $check_access = ['callable' => 'menu.default_tree_manipulators:checkAccess'];

    return [
      'empty list' => [
        [],
        [$check_node_access],
      ],
      'contains checkAccess' => [
        [$check_access],
        [$check_node_access, $check_access],
      ],
      'contains checkNodeAccess already' => [
        [$check_node_access],
        [$check_node_access],
      ],
      'entry with no callable key' => [
        [['id' => 'foo']],
        [$check_node_access, ['id' => 'foo']],
      ],
    ];
  }

  /* --------------------------------------------------------------------
   * preprocessMenu
   * ----------------------------------------------------------------- */

  /**
   * Tests items and the destination fetch are untouched off the account menu.
   */
  public function testPreprocessMenuSkipsNonAccountMenu(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->expects($this->never())->method('get');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $variables = ['menu_name' => 'main', 'items' => ['x']];
    $before = $variables;
    $hooks->preprocessMenu($variables);

    $this->assertSame($before, $variables);
  }

  /**
   * Tests items are untouched, with no notice, when menu_name is absent.
   */
  public function testPreprocessMenuSkipsWhenMenuNameAbsent(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->expects($this->never())->method('get');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $variables = ['items' => ['x']];
    $before = $variables;
    $hooks->preprocessMenu($variables);

    $this->assertSame($before, $variables);
  }

  /**
   * Tests items are untouched when the destination is empty.
   */
  public function testPreprocessMenuSkipsEmptyDestination(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $variables = ['menu_name' => 'account', 'items' => ['x']];
    $before = $variables;
    $hooks->preprocessMenu($variables);

    $this->assertSame($before, $variables);
  }

  /**
   * Tests items are untouched for a SAML-login-path destination.
   */
  public function testPreprocessMenuSkipsSamlLoginDestination(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/saml/login?destination=%2Fnode%2F1');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $variables = ['menu_name' => 'account', 'items' => ['x']];
    $before = $variables;
    $hooks->preprocessMenu($variables);

    $this->assertSame($before, $variables);
  }

  /**
   * Tests the destination query is set on a routed SAML login link.
   */
  public function testPreprocessMenuSetsDestinationOnRoutedSamlLink(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $url = $this->mockUrl(TRUE, 'samlauth.saml_controller_login', FALSE, '');
    $url->method('getOption')->with('query')->willReturn(NULL);
    $url->expects($this->once())
      ->method('setOption')
      ->with('query', ['destination' => '/node/5']);

    $variables = [
      'menu_name' => 'account',
      'items' => [
        ['title' => 'Log in', 'url' => $url],
      ],
    ];
    $hooks->preprocessMenu($variables);
  }

  /**
   * Tests the destination is applied recursively to a link nested in below.
   */
  public function testPreprocessMenuSetsDestinationRecursively(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $url = $this->mockUrl(TRUE, 'samlauth.saml_controller_login', FALSE, '');
    $url->method('getOption')->with('query')->willReturn(NULL);
    $url->expects($this->once())
      ->method('setOption')
      ->with('query', ['destination' => '/node/5']);

    $variables = [
      'menu_name' => 'account',
      'items' => [
        [
          'title' => 'Parent',
          'below' => [
            ['title' => 'Log in', 'url' => $url],
          ],
        ],
      ],
    ];
    $hooks->preprocessMenu($variables);
  }

  /**
   * Tests the destination is set for an unrouted internal SAML login link.
   */
  public function testPreprocessMenuSetsDestinationOnUnroutedSamlLink(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $url = $this->mockUrl(FALSE, NULL, FALSE, '/saml/login');
    $url->method('getOption')->with('query')->willReturn(NULL);
    $url->expects($this->once())
      ->method('setOption')
      ->with('query', ['destination' => '/node/5']);

    $variables = [
      'menu_name' => 'account',
      'items' => [
        ['title' => 'Log in', 'url' => $url],
      ],
    ];
    $hooks->preprocessMenu($variables);
  }

  /**
   * Tests an external URL is left untouched.
   */
  public function testPreprocessMenuSkipsExternalUrl(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $url = $this->mockUrl(FALSE, NULL, TRUE, 'https://example.com');
    $url->expects($this->never())->method('setOption');

    $variables = [
      'menu_name' => 'account',
      'items' => [
        ['title' => 'External', 'url' => $url],
      ],
    ];
    $before = $variables;
    $hooks->preprocessMenu($variables);

    $this->assertSame($before, $variables);
  }

  /**
   * Tests an existing query option is preserved and destination is added.
   */
  public function testPreprocessMenuPreservesExistingQueryOption(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $url = $this->mockUrl(TRUE, 'samlauth.saml_controller_login', FALSE, '');
    $url->method('getOption')->with('query')->willReturn(['foo' => 'bar']);
    $url->expects($this->once())
      ->method('setOption')
      ->with('query', ['foo' => 'bar', 'destination' => '/node/5']);

    $variables = [
      'menu_name' => 'account',
      'items' => [
        ['title' => 'Log in', 'url' => $url],
      ],
    ];
    $hooks->preprocessMenu($variables);
  }

  /**
   * Tests an item with no url key is skipped without a notice.
   */
  public function testPreprocessMenuSkipsItemWithNoUrlKey(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $variables = [
      'menu_name' => 'account',
      'items' => [
        ['title' => 'No url'],
      ],
    ];
    $before = $variables;
    $hooks->preprocessMenu($variables);

    $this->assertSame($before, $variables);
  }

  /**
   * Tests no TypeError is thrown when the account menu has no items key.
   *
   * Pins a regression fix documented in section 3.3 of the spec: passing
   * $variables['items'] by reference into addLoginDestination(), which is
   * typed `array`, autovivified a NULL key and fataled with a TypeError.
   */
  public function testPreprocessMenuNoItemsKeyReturnsCleanly(): void {
    $redirect_destination = $this->createMock(RedirectDestinationInterface::class);
    $redirect_destination->method('get')->willReturn('/node/5');
    $hooks = $this->buildHooks('oit', $redirect_destination);

    $variables = ['menu_name' => 'account'];
    $hooks->preprocessMenu($variables);

    // The guard returns before the by-reference pass, so no 'items' key is
    // invented on the caller's variables.
    $this->assertSame(['menu_name' => 'account'], $variables);
  }

  /* --------------------------------------------------------------------
   * blockBuildAlter
   * ----------------------------------------------------------------- */

  /**
   * Tests the render cache contexts added for account menu block variants.
   */
  #[DataProvider('blockBuildProvider')]
  public function testBlockBuildAlter(string $plugin_id, ?array $expected_contexts): void {
    $block = $this->createMock(BlockPluginInterface::class);
    $block->method('getPluginId')->willReturn($plugin_id);
    $hooks = $this->buildHooks();

    $build = [];
    $hooks->blockBuildAlter($build, $block);

    if ($expected_contexts === NULL) {
      $this->assertSame([], $build);
    }
    else {
      $this->assertSame($expected_contexts, $build['#cache']['contexts']);
    }
  }

  /**
   * Data provider for blockBuildAlter cases.
   *
   * @return array
   *   Test cases.
   */
  public static function blockBuildProvider(): array {
    return [
      'account block' => ['system_menu_block:account', ['url.path', 'url.query_args']],
      'main block untouched' => ['system_menu_block:main', NULL],
      'account_links family match' => ['system_menu_block:account_links', ['url.path', 'url.query_args']],
    ];
  }

}
