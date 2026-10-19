<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\EntityHooks;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\oit\Services\HeroImage;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the EntityHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(EntityHooks::class)]
#[CoversMethod(EntityHooks::class, 'entityCreate')]
#[CoversMethod(EntityHooks::class, 'entityPresave')]
#[CoversMethod(EntityHooks::class, 'entityView')]
class EntityHooksTest extends DrupalUnitTestCase {

  /**
   * The hooks object under test.
   *
   * @var \Drupal\oit\Hook\EntityHooks
   */
  protected EntityHooks $hooks;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel factory mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Teams alert mock.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  protected TeamsAlert $teamsAlert;

  /**
   * The hero image service mock.
   *
   * @var \Drupal\oit\Services\HeroImage
   */
  protected HeroImage $heroImage;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->teamsAlert = $this->createMock(TeamsAlert::class);
    $this->heroImage = $this->createMock(HeroImage::class);
    $this->requestStack = new RequestStack();
    $this->hooks = new EntityHooks($this->entityTypeManager, $this->loggerFactory, $this->teamsAlert, $this->heroImage, $this->requestStack);
  }

  /**
   * Tests presave is registered only as an object-oriented hook.
   */
  public function testEntityPresaveHookRegistration(): void {
    require_once __DIR__ . '/../../../../oit.module';

    $method = new \ReflectionMethod(EntityHooks::class, 'entityPresave');
    $attributes = $method->getAttributes(Hook::class);
    $this->assertCount(1, $attributes);
    $this->assertSame('entity_presave', $attributes[0]->newInstance()->hook);
    $this->assertFalse(function_exists('oit_entity_presave'));
    $this->assertFalse(function_exists('oit_news_update'));
    $this->assertFalse(function_exists('oit_servicealert_update'));
  }

  /**
   * Tests picture alt text is updated from the title.
   */
  public function testEntityPresaveSetsPictureAlt(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('other');
    $picture = $this->createMock(FieldItemListInterface::class);
    $picture->expects($this->once())
      ->method('__set')
      ->with('alt', 'Picture title');
    $title = $this->createMock(FieldItemListInterface::class);
    $title->method('__get')->with('value')->willReturn('Picture title');

    $entity = $this->createMock(Node::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('pictures');
    $entity->method('bundle')->willReturn('pictures');
    $entity->method('__get')->with('field_finished_picture')->willReturn($picture);
    $entity->method('get')->with('title')->willReturn($title);

    $this->hooks->entityPresave($entity);
  }

  /**
   * Tests news saves still call the helper to schedule unpublishing.
   */
  public function testEntityPresaveUpdatesNews(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('other');
    $meta = $this->createMock(FieldItemListInterface::class);
    $meta->method('getValue')->willReturn([
      ['value' => '{"og_image":"/image.png","twitter_cards_image":"/image.png"}'],
    ]);
    $archive = $this->createMock(FieldItemListInterface::class);
    $archive->method('getValue')->willReturn([['value' => 2]]);

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('news');
    $entity->method('bundle')->willReturn('news');
    $entity->method('get')->willReturnMap([
      ['field_meta_tags', $meta],
      ['field_news_archive', $archive],
    ]);
    $before = strtotime('100 days');
    $unpublish_on = NULL;
    $entity->expects($this->once())->method('set')
      ->with('unpublish_on', $this->callback(function ($value) use (&$unpublish_on) {
        $unpublish_on = $value;
        return is_int($value);
      }));

    $this->hooks->entityPresave($entity);

    $this->assertGreaterThanOrEqual($before, $unpublish_on);
    $this->assertLessThanOrEqual(strtotime('100 days'), $unpublish_on);
  }

  /**
   * Tests service alert saves still call the metadata helper.
   */
  public function testEntityPresaveUpdatesServiceAlert(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('other');
    $meta = $this->createMock(FieldItemListInterface::class);
    $meta->method('getValue')->willReturn([
      ['value' => '{"og_image":"/image.png","twitter_cards_image":"/image.png"}'],
    ]);

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('service_alert');
    $entity->method('bundle')->willReturn('service_alert');
    $entity->expects($this->once())->method('get')
      ->with('field_meta_tags')->willReturn($meta);
    $entity->expects($this->never())->method('set');

    $this->hooks->entityPresave($entity);
  }

  /**
   * Tests news without a hero image leaves metadata and scheduling unchanged.
   */
  public function testEntityPresaveNewsWithoutHeroImage(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('other');
    $meta = $this->createMock(FieldItemListInterface::class);
    $meta->method('getValue')->willReturn([['value' => '{}']]);
    $archive = $this->createMock(FieldItemListInterface::class);
    $archive->method('getValue')->willReturn([['value' => 0]]);

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('news');
    $entity->method('bundle')->willReturn('news');
    $entity->method('get')->willReturnMap([
      ['field_meta_tags', $meta],
      ['field_news_archive', $archive],
    ]);
    $entity->expects($this->never())->method('set');
    $this->heroImage->expects($this->once())->method('getFileId')
      ->with($entity)->willReturn(NULL);

    $this->hooks->entityPresave($entity);
  }

  /**
   * Tests service alerts use the current host for the default social image.
   */
  public function testEntityPresaveServiceAlertDefaultImage(): void {
    $this->requestStack->push(Request::create('https://oit.example.com'));

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('other');
    $meta = $this->createMock(FieldItemListInterface::class);
    $meta->method('getValue')->willReturn([['value' => '{}']]);
    $category = $this->createMock(FieldItemListInterface::class);
    $category->method('getValue')->willReturn([['target_id' => 5]]);
    $image = $this->createMock(FieldItemListInterface::class);
    $image->method('getValue')->willReturn([]);
    $term = $this->createMock(TermInterface::class);
    $term->method('get')->with('field_twitter_image')->willReturn($image);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(5)->willReturn($term);
    $this->entityTypeManager->expects($this->once())->method('getStorage')
      ->with('taxonomy_term')->willReturn($storage);

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('service_alert');
    $entity->method('bundle')->willReturn('service_alert');
    $entity->method('get')->willReturnMap([
      ['field_meta_tags', $meta],
      ['field_service_alert_dash_cat', $category],
    ]);
    $url = 'https://oit.example.com/sites/default/files/sa_images/sa_other.png';
    $entity->expects($this->once())->method('set')
      ->with('field_meta_tags', serialize([
        'twitter_cards_image' => $url,
        'og_image' => $url,
      ]));

    $this->hooks->entityPresave($entity);
  }

  /**
   * Tests the access control field is set for a new webform node type.
   */
  public function testEntityCreateSetsAccessControlForWebformNodeType(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('webform');
    $entity->expects($this->once())
      ->method('set')
      ->with('field_access_control_2', '475');

    $this->hooks->entityCreate($entity);
  }

  /**
   * Tests nothing is set for a non-webform node type.
   */
  public function testEntityCreateSkipsNonWebformNodeType(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $calls = [];
    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('page');
    $entity->method('set')->willReturnCallback(
      function (string $name, $value) use (&$calls): void {
        $calls[] = [$name, $value];
      }
    );

    $this->hooks->entityCreate($entity);

    $this->assertSame([], $calls);
  }

  /**
   * Tests non-node-type entities are skipped without reaching getType().
   */
  public function testEntityCreateSkipsNonNodeTypeBundleEntityType(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('taxonomy_vocabulary');

    $calls = [];
    $recorder = function (string $name, $value = NULL) use (&$calls): void {
      $calls[] = $name;
    };
    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturnCallback(
      function () use (&$calls) {
        $calls[] = 'getType';
        return 'webform';
      }
    );
    $entity->method('set')->willReturnCallback($recorder);

    $this->hooks->entityCreate($entity);

    $this->assertSame([], $calls);
  }

  /**
   * Tests a deleted webform triggers an alert and a log entry.
   */
  public function testEntityViewDeletedWebformTriggersAlertAndLog(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $field_item_list = $this->createMock(FieldItemListInterface::class);
    $field_item_list->method('getValue')->willReturn([
      [
        'target_id' => 5,
      ],
    ]);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('load')->with(5)->willReturn(NULL);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('webform')
      ->willReturn($entity_storage);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->expects($this->once())
      ->method('get')
      ->with('oit')
      ->willReturn($logger);

    $message = 'Webform no longer exists but is set on node: 42';
    $this->teamsAlert->expects($this->once())
      ->method('sendMessage')
      ->with($message, ['live']);
    $logger->expects($this->once())
      ->method('error')
      ->with($message);

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('webform');
    $entity->method('get')->with('webform')->willReturn($field_item_list);
    $entity->method('id')->willReturn(42);

    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $this->hooks->entityView($build, $entity, $display, 'full');
  }

  /**
   * Tests an existing webform does nothing.
   */
  public function testEntityViewExistingWebformDoesNothing(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $field_item_list = $this->createMock(FieldItemListInterface::class);
    $field_item_list->method('getValue')->willReturn([
      [
        'target_id' => 5,
      ],
    ]);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('load')->with(5)->willReturn($this->createMock(EntityInterface::class));
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('webform')
      ->willReturn($entity_storage);

    $this->loggerFactory->expects($this->never())
      ->method('get');
    $this->teamsAlert->expects($this->never())
      ->method('sendMessage');

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('webform');
    $entity->method('get')->with('webform')->willReturn($field_item_list);

    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $this->hooks->entityView($build, $entity, $display, 'full');
  }

  /**
   * Tests a webform target ID of zero does nothing.
   */
  public function testEntityViewWebformTargetIdOfZeroDoesNothing(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $field_item_list = $this->createMock(FieldItemListInterface::class);
    $field_item_list->method('getValue')->willReturn([
      [
        'target_id' => 0,
      ],
    ]);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('load')->with(0)->willReturn(NULL);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('webform')
      ->willReturn($entity_storage);

    $this->teamsAlert->expects($this->never())
      ->method('sendMessage');
    $this->loggerFactory->expects($this->never())
      ->method('get');

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('webform');
    $entity->method('get')->with('webform')->willReturn($field_item_list);

    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $this->hooks->entityView($build, $entity, $display, 'full');
  }

  /**
   * Tests an empty webform field value does nothing.
   */
  public function testEntityViewEmptyWebformFieldValueDoesNothing(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $field_item_list = $this->createMock(FieldItemListInterface::class);
    $field_item_list->method('getValue')->willReturn([]);

    $this->entityTypeManager->expects($this->never())
      ->method('getStorage');
    $this->teamsAlert->expects($this->never())
      ->method('sendMessage');

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('webform');
    $entity->method('get')->with('webform')->willReturn($field_item_list);

    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $this->hooks->entityView($build, $entity, $display, 'full');
  }

  /**
   * Tests a non-webform node type does nothing.
   */
  public function testEntityViewNonWebformNodeTypeDoesNothing(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('node_type');

    $this->entityTypeManager->expects($this->never())
      ->method('getStorage');
    $this->teamsAlert->expects($this->never())
      ->method('sendMessage');

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getType')->willReturn('page');

    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $this->hooks->entityView($build, $entity, $display, 'full');
  }

  /**
   * Tests a non node_type bundle entity does nothing.
   */
  public function testEntityViewNonNodeTypeBundleEntityDoesNothing(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getBundleEntityType')->willReturn('taxonomy_vocabulary');

    $this->entityTypeManager->expects($this->never())
      ->method('getStorage');
    $this->teamsAlert->expects($this->never())
      ->method('sendMessage');

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);

    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $this->hooks->entityView($build, $entity, $display, 'full');
  }

}
