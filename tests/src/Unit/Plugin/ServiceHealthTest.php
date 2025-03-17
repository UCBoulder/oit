<?php

namespace Drupal\Tests\oit\Unit\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\oit\Plugin\ServiceHealth;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the service health plugin.
 *
 * @group oit
 * @coversDefaultClass \Drupal\oit\Plugin\ServiceHealth
 */
class ServiceHealthTest extends UnitTestCase {

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateFormatter;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The service health plugin.
   *
   * @var \Drupal\oit\Plugin\ServiceHealth
   */
  protected $serviceHealth;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the translation service.
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnArgument(0);
    $translation->method('translateString')
      ->willReturnCallback(function ($string) {
        return $string->getUntranslatedString();
      });

    // Create a container with the translation service.
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    // Create mocks for all dependencies.
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Create the service health plugin with mocked dependencies.
    $this->serviceHealth = new ServiceHealth(
      $this->configFactory,
      $this->dateFormatter,
      $this->entityTypeManager
    );
  }

  /**
   * Tests the service health status key mapping.
   *
   * @covers ::serviceHealthStatusByKey
   */
  public function testServiceHealthStatusByKey() {
    $status_key = $this->serviceHealth->serviceHealthStatusByKey();

    $this->assertIsArray($status_key);
    $this->assertArrayHasKey(0, $status_key);
    $this->assertArrayHasKey(1, $status_key);
    $this->assertArrayHasKey(2, $status_key);
    $this->assertEquals('No service issue', (string) $status_key[0]);
    $this->assertEquals('Maintenance scheduled/ongoing', (string) $status_key[1]);
    $this->assertEquals('Service issue', (string) $status_key[2]);
  }

  /**
   * Tests the service health lookup functionality.
   *
   * @covers ::serviceHealthLookup
   */
  public function testServiceHealthLookup() {
    // Mock the config object.
    $config = $this->getMockBuilder('Drupal\Core\Config\ImmutableConfig')
      ->disableOriginalConstructor()
      ->getMock();
    $config->expects($this->once())
      ->method('get')
      ->with('settings')
      ->willReturn([
        'allowed_values' => [
          ['value' => 'test1', 'label' => 'Test Service 1'],
          ['value' => 'test2', 'label' => 'Test Service 2'],
        ],
      ]);

    // Mock the config factory to return our mocked config.
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('field.storage.node.field_service_dashboard_category')
      ->willReturn($config);

    // Mock the entity storage and query.
    $query = $this->getMockBuilder('Drupal\Core\Entity\Query\QueryInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $query->expects($this->any())
      ->method('condition')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('accessCheck')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('sort')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('execute')
      ->willReturn([]);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);

    // Test the service health lookup.
    $result = $this->serviceHealth->serviceHealthLookup();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('0-Test Service 1', $result);
    $this->assertArrayHasKey('0-Test Service 2', $result);
    $this->assertEquals([
      'service' => 'Test Service 1',
      'status' => 0,
      'link' => '',
      'button' => '',
      'last_update' => '',
    ], $result['0-Test Service 1']);
  }

  /**
   * Tests the duplicate removal functionality.
   *
   * @covers ::removeDuplicates
   */
  public function testRemoveDuplicates() {
    $test_data = [
      '2-Service1' => [
        'service' => 'Service1',
        'status' => 2,
        'link' => 'link2',
        'button' => 'button2',
        'last_update' => 'update2',
      ],
      '1-Service1' => [
        'service' => 'Service1',
        'status' => 1,
        'link' => 'link1',
        'button' => 'button1',
        'last_update' => 'update1',
      ],
      '0-Service2' => [
        'service' => 'Service2',
        'status' => 0,
        'link' => '',
        'button' => '',
        'last_update' => '',
      ],
    ];

    $result = $this->serviceHealth->removeDuplicates($test_data);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('Service1', $result);
    $this->assertArrayHasKey('Service2', $result);
    $this->assertEquals(2, $result['Service1']['status']);
    $this->assertEquals(0, $result['Service2']['status']);
  }

}
