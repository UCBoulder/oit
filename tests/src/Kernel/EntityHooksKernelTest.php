<?php

namespace Drupal\Tests\oit\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\oit\Hook\EntityHooks;

/**
 * Kernel test proving EntityHooks::entityCreate() against a real node.
 *
 * A mock EntityInterface can prove set() was called with the right
 * arguments, but only a real save/reload round trip proves the field
 * actually exists and accepts the value (spec section 6). The field
 * storage and instance are created directly in setUp(), per the section 6
 * "Decision: option 1", rather than relying on the site's config/default,
 * which this module cannot assume is present (section 6.1 covers that
 * separately).
 */
#[Group('oit')]
#[RunTestsInSeparateProcesses]
#[CoversMethod(EntityHooks::class, 'entityCreate')]
class EntityHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'options',
    'key',
    'encrypt',
    'path_alias',
    'oit',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node']);

    NodeType::create(['type' => 'webform', 'name' => 'Webform'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_access_control_2',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_access_control_2',
      'entity_type' => 'node',
      'bundle' => 'webform',
      'label' => 'Access Control',
    ])->save();
  }

  /**
   * Tests a real 'webform' node stores '475' in field_access_control_2.
   *
   * The entity_create hook fires on Node::create(), before the node is
   * saved, which is when EntityHooks::entityCreate() sets the field.
   */
  public function testWebformNodeGetsAccessControlDefault(): void {
    $node = Node::create([
      'type' => 'webform',
      'title' => 'Test webform node',
    ]);
    $node->save();

    $reloaded = Node::load($node->id());
    $this->assertSame('475', $reloaded->get('field_access_control_2')->value);
  }

}
