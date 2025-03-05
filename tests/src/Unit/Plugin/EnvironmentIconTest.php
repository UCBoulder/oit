<?php

namespace Drupal\Tests\oit\Unit\Plugin;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\oit\Plugin\EnvironmentIcon;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;

/**
 * Unit tests for EnvironmentIcon plugin.
 *
 * @group oit
 * @coversDefaultClass \Drupal\oit\Plugin\EnvironmentIcon
 */
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
   *
   * @covers ::__construct
   * @covers ::getEnv
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
   *
   * @covers ::__construct
   * @covers ::getEnv
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
   *
   * @covers ::__construct
   * @covers ::getEnv
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
   *
   * @covers ::__construct
   * @covers ::getEnv
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
   *
   * @covers ::__construct
   * @covers ::getEnv
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
