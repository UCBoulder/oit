<?php

namespace Drupal\Tests\oit\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oit\Hook\TokenHooks;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test proving hook attribute discovery for TokenHooks.
 *
 * A mock-based unit test can prove TokenHooks::tokens() dispatches
 * correctly, but only a real \Drupal::token()->replacePlain() round trip
 * proves that the #[Hook('token_info')] and #[Hook('tokens')] attributes
 * are actually discovered and wired up by Drupal's hook system.
 * replacePlain() is used rather than replace() because replace() wraps
 * every non-Markup replacement value in HtmlEscapedText, which would
 * double-escape the value TokenHooks already ran through Html::escape().
 * Fields are created directly in setUp(), per the EntityHooksKernelTest
 * pattern, so this test does not depend on config/default.
 */
#[Group('oit')]
#[RunTestsInSeparateProcesses]
#[CoversMethod(TokenHooks::class, 'tokenInfo')]
#[CoversMethod(TokenHooks::class, 'tokens')]
class TokenHooksKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'node']);

    FieldStorageConfig::create([
      'field_name' => 'field_user_name',
      'entity_type' => 'user',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_user_name',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'User Name',
    ])->save();
  }

  /**
   * Tests token_info exposes the oittoken type without legacy tokens.
   */
  public function testTokenInfoExposesType(): void {
    $info = \Drupal::token()->getInfo();

    $this->assertArrayHasKey('oittoken', $info['types']);
    $this->assertArrayNotHasKey('sa_title', $info['tokens']['oittoken']);
    $this->assertArrayNotHasKey('sa_status', $info['tokens']['oittoken']);
  }

  /**
   * Tests who_i_is for a logged-in user with an HTML-bearing name.
   */
  public function testWhoIisForLoggedInUser(): void {
    $user = User::create([
      'name' => 'ralphie',
      'status' => 1,
      'field_user_name' => 'Ralphie <b>',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $metadata = new BubbleableMetadata();
    $result = \Drupal::token()->replacePlain('[oittoken:who_i_is]', [], [], $metadata);

    $this->assertSame('Ralphie &lt;b&gt;', $result);
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertContains('user:' . $user->id(), $metadata->getCacheTags());
  }

  /**
   * Tests who_i_is returns an empty string for anonymous users.
   */
  public function testWhoIisForAnonymous(): void {
    $metadata = new BubbleableMetadata();
    $result = \Drupal::token()->replacePlain('[oittoken:who_i_is]', [], [], $metadata);

    $this->assertSame('', $result);
    $this->assertContains('user', $metadata->getCacheContexts());
  }

}
