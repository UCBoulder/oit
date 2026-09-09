<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\EntityHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the EntityHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(EntityHooks::class)]
#[CoversMethod(EntityHooks::class, 'entityCreate')]
class EntityHooksTest extends DrupalUnitTestCase {

  /**
   * The hooks object under test.
   *
   * @var \Drupal\oit\Hook\EntityHooks
   */
  protected EntityHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->hooks = new EntityHooks();
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

}
