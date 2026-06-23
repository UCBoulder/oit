<?php

namespace Drupal\Tests\oit\Unit\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\oit\Services\PortfolioData;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the PortfolioData service.
 */
#[Group('oit')]
#[CoversClass(PortfolioData::class)]
#[CoversMethod(PortfolioData::class, 'getSheetData')]
#[CoversMethod(PortfolioData::class, 'refresh')]
class PortfolioDataTest extends UnitTestCase {

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheBackend;

  /**
   * The mocked cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cacheBackend = $this->createMock(CacheBackendInterface::class);
    $this->cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
  }

  /**
   * Builds a testable service with a canned fetchRaw() result.
   *
   * @param array $fetched
   *   The data fetchRaw() should return.
   *
   * @return \Drupal\oit\Services\PortfolioData
   *   The testable service.
   */
  protected function getService(array $fetched): PortfolioData {
    return new class($this->cacheBackend, $this->cacheTagsInvalidator, $fetched) extends PortfolioData {

      /**
       * Canned fetch result.
       *
       * @var array
       */
      private $fetched;

      public function __construct($cache_backend, $cache_tags_invalidator, array $fetched) {
        parent::__construct($cache_backend, $cache_tags_invalidator);
        $this->fetched = $fetched;
      }

      /**
       * {@inheritdoc}
       */
      protected function fetchRaw(): array {
        return $this->fetched;
      }

    };
  }

  /**
   * Cache hit returns cached data without fetching or writing.
   */
  public function testGetSheetDataCacheHit(): void {
    $cached = (object) ['data' => [['row']]];
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->with(PortfolioData::CID)
      ->willReturn($cached);
    $this->cacheBackend->expects($this->never())->method('set');
    $this->cacheTagsInvalidator->expects($this->never())->method('invalidateTags');

    $service = $this->getService([['should-not-be-used']]);
    $this->assertSame([['row']], $service->getSheetData());
  }

  /**
   * Cold miss with non-empty fetch writes 26h TTL and returns data, no tag.
   */
  public function testGetSheetDataColdMissNonEmpty(): void {
    $data = [['a'], ['b']];
    $now = time();
    $this->cacheBackend->method('get')->willReturn(FALSE);
    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        PortfolioData::CID,
        $data,
        $this->callback(fn ($expire) => is_int($expire) && $expire >= $now + PortfolioData::TTL && $expire <= $now + PortfolioData::TTL + 2)
      );
    $this->cacheTagsInvalidator->expects($this->never())->method('invalidateTags');

    $service = $this->getService($data);
    $this->assertSame($data, $service->getSheetData());
  }

  /**
   * Cold miss with empty fetch does not write.
   */
  public function testGetSheetDataColdMissEmpty(): void {
    $this->cacheBackend->method('get')->willReturn(FALSE);
    $this->cacheBackend->expects($this->never())->method('set');
    $this->cacheTagsInvalidator->expects($this->never())->method('invalidateTags');

    $service = $this->getService([]);
    $this->assertSame([], $service->getSheetData());
  }

  /**
   * Refresh with non-empty fetch writes the cache and invalidates the tag once.
   */
  public function testRefreshNonEmpty(): void {
    $data = [['a'], ['b']];
    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        PortfolioData::CID,
        $data,
        $this->greaterThan(time())
      );
    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with([PortfolioData::TAG]);

    $service = $this->getService($data);
    $service->refresh();
  }

  /**
   * Refresh with empty fetch writes nothing and does not invalidate.
   */
  public function testRefreshEmpty(): void {
    $this->cacheBackend->expects($this->never())->method('set');
    $this->cacheTagsInvalidator->expects($this->never())->method('invalidateTags');

    $service = $this->getService([]);
    $service->refresh();
  }

}
