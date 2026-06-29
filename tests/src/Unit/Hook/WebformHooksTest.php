<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\oit\Hook\WebformHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the WebformHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(WebformHooks::class)]
#[CoversMethod(WebformHooks::class, 'webformAccessRulesAlter')]
class WebformHooksTest extends DrupalUnitTestCase {

  /**
   * Tests the create access rule roles are set to authenticated.
   */
  public function testWebformAccessRulesAlterSetsCreateRoles(): void {
    $access_rules = [
      'create' => [
        'roles' => ['admin'],
        'users' => [1],
      ],
    ];
    $hooks = new WebformHooks();
    $hooks->webformAccessRulesAlter($access_rules);

    $this->assertEquals(['authenticated'], $access_rules['create']['roles']);
    // Other keys on the create rule are left untouched.
    $this->assertEquals([1], $access_rules['create']['users']);
  }

  /**
   * Tests nothing is altered when there is no create access rule.
   */
  public function testWebformAccessRulesAlterSkipsWithoutCreateRule(): void {
    $access_rules = [
      'update' => [
        'roles' => ['admin'],
      ],
    ];
    $hooks = new WebformHooks();
    $hooks->webformAccessRulesAlter($access_rules);

    $this->assertEquals(['update' => ['roles' => ['admin']]], $access_rules);
  }

}
