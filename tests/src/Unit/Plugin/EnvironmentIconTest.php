<?php

namespace Drupal\Tests\oit\Unit\Plugin;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\oit\Plugin\EnvironmentIcon;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for EnvironmentIcon plugin.
 */
#[Group('oit')]
#[CoversClass(EnvironmentIcon::class)]
#[CoversMethod(EnvironmentIcon::class, '__construct')]
#[CoversMethod(EnvironmentIcon::class, 'getEnv')]
class EnvironmentIconTest extends DrupalUnitTestCase {

  /**
   * The mocked account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->account = $this->createMock(AccountProxyInterface::class);
  }

  /**
   * Tests local environment icon.
   */
  public function testLocalEnvironmentIcon(): void {
    // Set up environment variable.
    putenv('PANTHEON_ENVIRONMENT=local');

    // Mock account roles.
    $this->account->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $environmentIcon = new EnvironmentIcon($this->account);
    $this->assertEquals('✅🐕 ', $environmentIcon->getEnv());

    // Test LANDO environment as well.
    putenv('PANTHEON_ENVIRONMENT=LANDO');
    $environmentIcon = new EnvironmentIcon($this->account);
    $this->assertEquals('✅🐕 ', $environmentIcon->getEnv());
  }

  /**
   * Tests dev environment icon.
   */
  public function testDevEnvironmentIcon(): void {
    putenv('PANTHEON_ENVIRONMENT=dev');

    // Mock account roles.
    $this->account->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $environmentIcon = new EnvironmentIcon($this->account);
    $this->assertEquals('🟢🐕 ', $environmentIcon->getEnv());
  }

  /**
   * Tests test environment icon.
   */
  public function testTestEnvironmentIcon(): void {
    putenv('PANTHEON_ENVIRONMENT=test');

    // Mock account roles.
    $this->account->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $environmentIcon = new EnvironmentIcon($this->account);
    $this->assertEquals('🟡🐕 ', $environmentIcon->getEnv());
  }

  /**
   * Tests live environment icon with administrator.
   */
  public function testLiveEnvironmentWithAdminIcon(): void {
    putenv('PANTHEON_ENVIRONMENT=live');

    // Mock account roles with administrator.
    $this->account->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated', 'administrator']);

    $environmentIcon = new EnvironmentIcon($this->account);
    $this->assertEquals('🔴🐕 ', $environmentIcon->getEnv());
  }

  /**
   * Tests live environment icon without administrator.
   */
  public function testLiveEnvironmentWithoutAdminIcon(): void {
    putenv('PANTHEON_ENVIRONMENT=live');

    // Mock account roles without administrator.
    $this->account->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated', 'editor']);

    $environmentIcon = new EnvironmentIcon($this->account);
    $this->assertEquals('', $environmentIcon->getEnv());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Restore environment variables.
    putenv('PANTHEON_ENVIRONMENT=');
  }

}
