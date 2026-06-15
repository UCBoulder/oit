<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\oit\Hook\HtmlHooks;
use Drupal\oit\Plugin\EnvironmentIcon;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the HtmlHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(HtmlHooks::class)]
#[CoversMethod(HtmlHooks::class, '__construct')]
#[CoversMethod(HtmlHooks::class, 'preprocessHtml')]
class HtmlHooksTest extends DrupalUnitTestCase {

  /**
   * The mocked theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $themeManager;

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The mocked environment icon service.
   *
   * @var \Drupal\oit\Plugin\EnvironmentIcon|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $environmentIcon;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->themeManager = $this->createMock(ThemeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->environmentIcon = $this->createMock(EnvironmentIcon::class);
  }

  /**
   * Sets the active theme name on the mocked theme manager.
   *
   * @param string $name
   *   The active theme machine name.
   */
  protected function setActiveTheme(string $name): void {
    $activeTheme = $this->createMock(ActiveTheme::class);
    $activeTheme->expects($this->any())
      ->method('getName')
      ->willReturn($name);
    $this->themeManager->expects($this->any())
      ->method('getActiveTheme')
      ->willReturn($activeTheme);
  }

  /**
   * Tests the environment icon is prepended to the title on the gin theme.
   */
  public function testPreprocessHtmlSetsIconOnGinTheme(): void {
    $this->setActiveTheme('gin');
    $this->environmentIcon->expects($this->once())
      ->method('getEnv')
      ->willReturn('🟢🐕 ');
    $this->currentUser->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $variables = [
      'head_title' => [
        'title' => 'My Page',
        'name' => 'OIT',
      ],
    ];
    $hooks = new HtmlHooks($this->themeManager, $this->currentUser, $this->environmentIcon);
    $hooks->preprocessHtml($variables);

    $this->assertEquals('🟢🐕 My Page | OIT', $variables['head_title']);
  }

  /**
   * Tests the title is untouched when the active theme is not gin.
   */
  public function testPreprocessHtmlSkipsIconOnNonGinTheme(): void {
    $this->setActiveTheme('olivero');
    $this->environmentIcon->expects($this->never())
      ->method('getEnv');
    $this->currentUser->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $variables = [
      'head_title' => [
        'title' => 'My Page',
        'name' => 'OIT',
      ],
    ];
    $hooks = new HtmlHooks($this->themeManager, $this->currentUser, $this->environmentIcon);
    $hooks->preprocessHtml($variables);

    // The head_title array should remain unchanged.
    $this->assertEquals(['title' => 'My Page', 'name' => 'OIT'], $variables['head_title']);
  }

  /**
   * Tests the env class and library are attached for administrators.
   */
  public function testPreprocessHtmlAttachesEnvForAdministrator(): void {
    putenv('PANTHEON_ENVIRONMENT=live');
    $this->setActiveTheme('gin');
    $this->environmentIcon->expects($this->any())
      ->method('getEnv')
      ->willReturn('🔴🐕 ');
    $this->currentUser->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated', 'administrator']);

    $variables = ['head_title' => ['title' => 'My Page', 'name' => 'OIT']];
    $hooks = new HtmlHooks($this->themeManager, $this->currentUser, $this->environmentIcon);
    $hooks->preprocessHtml($variables);

    $this->assertContains('env-live', $variables['attributes']['class']);
    $this->assertContains('oit/oit_env', $variables['#attached']['library']);
  }

  /**
   * Tests no env class or library is attached for non-administrators.
   */
  public function testPreprocessHtmlSkipsEnvForNonAdministrator(): void {
    $this->setActiveTheme('gin');
    $this->environmentIcon->expects($this->any())
      ->method('getEnv')
      ->willReturn('🔴🐕 ');
    $this->currentUser->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated', 'editor']);

    $variables = ['head_title' => ['title' => 'My Page', 'name' => 'OIT']];
    $hooks = new HtmlHooks($this->themeManager, $this->currentUser, $this->environmentIcon);
    $hooks->preprocessHtml($variables);

    $this->assertArrayNotHasKey('attributes', $variables);
    $this->assertArrayNotHasKey('#attached', $variables);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    putenv('PANTHEON_ENVIRONMENT=');
  }

}
