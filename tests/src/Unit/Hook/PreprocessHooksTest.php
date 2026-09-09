<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\oit\Hook\PreprocessHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the PreprocessHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(PreprocessHooks::class)]
#[CoversMethod(PreprocessHooks::class, '__construct')]
#[CoversMethod(PreprocessHooks::class, 'preprocess')]
class PreprocessHooksTest extends DrupalUnitTestCase {

  /**
   * The mocked theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $themeManager;

  /**
   * The mocked current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->themeManager = $this->createMock(ThemeManagerInterface::class);
    $this->currentPath = $this->createMock(CurrentPathStack::class);
  }

  /**
   * Sets the active theme name on the mocked theme manager.
   *
   * @param string $name
   *   The active theme machine name.
   */
  protected function setActiveTheme(string $name): void {
    $active_theme = $this->createMock(ActiveTheme::class);
    $active_theme->method('getName')->willReturn($name);
    $this->themeManager->method('getActiveTheme')->willReturn($active_theme);
  }

  /**
   * Builds a PreprocessHooks instance with the mocked dependencies.
   *
   * @return \Drupal\oit\Hook\PreprocessHooks
   *   The configured hooks object.
   */
  protected function buildHooks(): PreprocessHooks {
    return new PreprocessHooks($this->themeManager, $this->currentPath);
  }

  /**
   * Tests the gingerbread library is attached on the node/add path.
   */
  public function testPreprocessAttachesGingerbreadOnNodeAdd(): void {
    $this->setActiveTheme('gin');
    $this->currentPath->method('getPath')->willReturn('/node/add');

    $variables = [];
    $this->buildHooks()->preprocess($variables, 'page');

    $this->assertSame(['oit/gingerbread'], $variables['#attached']['library']);
  }

  /**
   * Tests the gin_select library is attached for the tutorial add form.
   */
  public function testPreprocessAttachesGinSelectForTutorialAddForm(): void {
    $this->setActiveTheme('gin');
    $this->currentPath->method('getPath')->willReturn('/some/other/path');

    $variables = ['form' => ['#id' => 'node-tutorial-form']];
    $this->buildHooks()->preprocess($variables, 'page');

    $this->assertSame(['oit/gin_select'], $variables['#attached']['library']);
  }

  /**
   * Tests the gin_select library is attached for the tutorial edit form.
   */
  public function testPreprocessAttachesGinSelectForTutorialEditForm(): void {
    $this->setActiveTheme('gin');
    $this->currentPath->method('getPath')->willReturn('/some/other/path');

    $variables = ['form' => ['#id' => 'node-tutorial-edit-form']];
    $this->buildHooks()->preprocess($variables, 'page');

    $this->assertSame(['oit/gin_select'], $variables['#attached']['library']);
  }

  /**
   * Tests both libraries attach together, gingerbread first.
   */
  public function testPreprocessAttachesBothLibrariesInOrder(): void {
    $this->setActiveTheme('gin');
    $this->currentPath->method('getPath')->willReturn('/node/add');

    $variables = ['form' => ['#id' => 'node-tutorial-form']];
    $this->buildHooks()->preprocess($variables, 'page');

    $this->assertSame(['oit/gingerbread', 'oit/gin_select'], $variables['#attached']['library']);
  }

  /**
   * Tests no libraries attach when neither condition matches.
   */
  public function testPreprocessAttachesNothingWhenNoConditionsMatch(): void {
    $this->setActiveTheme('gin');
    $this->currentPath->method('getPath')->willReturn('/node/1');

    $variables = [];
    $this->buildHooks()->preprocess($variables, 'page');

    $this->assertArrayNotHasKey('#attached', $variables);
  }

  /**
   * Tests nothing runs and getPath() is never called on a non-gin theme.
   */
  public function testPreprocessSkipsNonGinTheme(): void {
    $this->setActiveTheme('claro');
    $this->currentPath->expects($this->never())->method('getPath');

    $variables = ['form' => ['#id' => 'node-tutorial-form']];
    $this->buildHooks()->preprocess($variables, 'page');

    $this->assertArrayNotHasKey('#attached', $variables);
  }

}
