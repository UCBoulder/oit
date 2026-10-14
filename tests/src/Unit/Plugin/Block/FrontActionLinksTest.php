<?php

namespace Drupal\Tests\oit\Unit\Plugin\Block;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oit\Plugin\Block\FrontActionLinks;
use Drupal\oit\Plugin\BlockUuidQuery;
use Drupal\shortcode_svg\ShortcodeIcon;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewExecutableFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the FrontActionLinks block.
 */
#[Group('oit')]
#[CoversClass(FrontActionLinks::class)]
class FrontActionLinksTest extends UnitTestCase {

  /**
   * The mocked shortcode svg icon service.
   *
   * @var \Drupal\shortcode_svg\ShortcodeIcon|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $shortcodeSvgIcon;

  /**
   * The mocked block UUID query service.
   *
   * @var \Drupal\oit\Plugin\BlockUuidQuery|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $blockUuidQuery;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->shortcodeSvgIcon = $this->createMock(ShortcodeIcon::class);
    $this->blockUuidQuery = $this->createMock(BlockUuidQuery::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Stub the "service_alerts" view lookup and executable factory so
    // Views::getView() resolves to a fake, inert executable without needing
    // a full views/database bootstrap.
    $viewStorage = $this->createMock(EntityStorageInterface::class);
    $viewStorage->method('load')
      ->with('service_alerts')
      ->willReturn($this->createMock(ViewEntityInterface::class));
    $this->entityTypeManager->method('getStorage')
      ->with('view')
      ->willReturn($viewStorage);

    $countQuery = new class {

      /**
       * Fakes Select::execute().
       *
       * @return $this
       *   Itself.
       */
      public function execute() {
        return $this;
      }

      /**
       * Fakes StatementInterface::fetchAssoc().
       *
       * @return array
       *   A zero count row.
       */
      public function fetchAssoc() {
        return ['expression' => 0];
      }

    };
    $queryPlugin = $this->getMockBuilder(QueryPluginBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['query'])
      ->getMock();
    $queryPlugin->method('query')
      ->willReturnCallback(function () use ($countQuery) {
        return new class($countQuery) {

          /**
           * The fake count query.
           *
           * @var object
           */
          protected $countQuery;

          /**
           * Constructs the fake select query.
           *
           * @param object $countQuery
           *   The fake count query.
           */
          public function __construct($countQuery) {
            $this->countQuery = $countQuery;
          }

          /**
           * Fakes Select::countQuery().
           *
           * @return object
           *   The fake count query.
           */
          public function countQuery() {
            return $this->countQuery;
          }

        };
      });

    $viewExecutable = $this->getMockBuilder(ViewExecutable::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['build'])
      ->getMock();
    $viewExecutable->expects($this->once())->method('build')->with('block_1');
    // ViewExecutable::$query is populated dynamically at execute time; set
    // it directly since build() is stubbed out above.
    $viewExecutable->query = $queryPlugin;

    $executableFactory = $this->getMockBuilder(ViewExecutableFactory::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();
    $executableFactory->method('get')->willReturn($viewExecutable);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('views.executable', $executableFactory);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    \Drupal::unsetContainer();
  }

  /**
   * Tests that build() calls setIcon() with bare-hex string widths.
   */
  public function testBuildUsesBareHexStringWidthIcons(): void {
    $calls = [];
    $this->shortcodeSvgIcon->method('setIcon')
      ->willReturnCallback(function (...$args) use (&$calls) {
        $calls[] = $args;
        return '<svg></svg>';
      });
    $this->shortcodeSvgIcon->method('getCacheTags')
      ->willReturn(['shortcode_svg.sprite']);

    $this->blockUuidQuery->method('getBidByUuid')->willReturn(NULL);
    $this->blockUuidQuery->method('loadBlock')->willReturn(['#markup' => '']);

    $block = new FrontActionLinks(
      [],
      'front_action_links',
      ['provider' => 'oit'],
      $this->entityTypeManager,
      $this->blockUuidQuery,
      $this->shortcodeSvgIcon,
    );
    $block->setStringTranslation($this->getStringTranslationStub());

    $block->build();

    $this->assertSame([
      ['exclamation-stroke', '20', 'fff', ''],
      ['megaphone', '20', 'fff', ''],
      ['bell-request', '20', 'fff', ''],
    ], $calls);

    foreach ($calls as $call) {
      // Width must be a string, and colors must be bare hex (no leading #).
      $this->assertIsString($call[1]);
      $this->assertStringStartsNotWith('#', $call[2]);
    }
  }

  /**
   * Tests that build() carries the sprite cache tags.
   */
  public function testBuildIncludesSpriteCacheTags(): void {
    $this->shortcodeSvgIcon->method('setIcon')->willReturn('<svg></svg>');
    $this->shortcodeSvgIcon->method('getCacheTags')
      ->willReturn(['shortcode_svg.sprite']);

    $this->blockUuidQuery->method('getBidByUuid')->willReturn(NULL);
    $this->blockUuidQuery->method('loadBlock')->willReturn(['#markup' => '']);

    $block = new FrontActionLinks(
      [],
      'front_action_links',
      ['provider' => 'oit'],
      $this->entityTypeManager,
      $this->blockUuidQuery,
      $this->shortcodeSvgIcon,
    );
    $block->setStringTranslation($this->getStringTranslationStub());

    $build = $block->build();

    $this->assertContains('node_type:service_alert', $build['#cache']['tags']);
    $this->assertContains('shortcode_svg.sprite', $build['#cache']['tags']);
  }

}
