<?php

namespace Drupal\Tests\oit\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oit\Hook\WebformHooks;
use Drupal\webform\Entity\Webform;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test running the access rules alter against real webform config.
 *
 * The existing WebformHooksTest already covers the method against plain
 * arrays. This test proves it against an access-rules array shaped exactly
 * like production config, imported directly from the site's
 * 'config/default' export, rather than a value the test author guessed at.
 */
#[Group('oit')]
#[RunTestsInSeparateProcesses]
#[CoversMethod(WebformHooks::class, 'webformAccessRulesAlter')]
class WebformHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'webform',
    'key',
    'encrypt',
    'path_alias',
    'oit',
  ];

  /**
   * The imported webform id.
   */
  protected const WEBFORM_ID = 'contact';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['filter']);

    $file = dirname(__DIR__, 7) . '/config/default/webform.webform.' . self::WEBFORM_ID . '.yml';
    if (!is_file($file)) {
      $this->markTestSkipped("config/default/webform.webform." . self::WEBFORM_ID . ".yml is not present in this checkout; this module's site config lives in a separate site repository.");
    }

    $data = Yaml::decode(file_get_contents($file));
    // Config entities require a UUID matching the active site's entity UUID
    // format; the imported UUID is fine as-is for a standalone Kernel test.
    \Drupal::configFactory()->getEditable('webform.webform.' . self::WEBFORM_ID)
      ->setData($data)
      ->save();
  }

  /**
   * Tests the create access rule is forced to 'authenticated' on real config.
   *
   * The imported 'contact' webform's create.roles includes 'anonymous' in
   * production. This proves the hook overrides that on a real config
   * entity, not just a hand-built array.
   */
  public function testAccessRulesAlteredOnRealWebformConfig(): void {
    $webform = Webform::load(self::WEBFORM_ID);
    $this->assertNotNull($webform, 'The imported webform config entity loaded.');

    $access_rules = $webform->getAccessRules();
    $this->assertContains('anonymous', $access_rules['create']['roles'], 'Fixture sanity check: production config grants anonymous create access.');

    $hooks = new WebformHooks();
    $hooks->webformAccessRulesAlter($access_rules);

    $this->assertSame(['authenticated'], $access_rules['create']['roles']);
  }

}
