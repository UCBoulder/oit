<?php

namespace Drupal\oit\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\oit\Plugin\GoogleSheetsFetch;

/**
 * Shared fetch/cache layer for the OIT Project Portfolio sheet data.
 *
 * Used by both the PortfolioBlock (read path) and the portfolio_refresh cron
 * job (write path) so the remote Google Sheets fetch is performed off the
 * request-render path and the cache is warmed on a schedule.
 */
class PortfolioData {

  /**
   * The Google Sheets document key.
   */
  const SHEET_KEY = '1k4-Csp29uLZbh_g2nhuhpq3dVBgZ6zWFK20BXP1rL_s';

  /**
   * The Google Sheets tab GID.
   */
  const SHEET_GID = 0;

  /**
   * The cache ID for the raw fetched sheet data.
   */
  const CID = 'oit:portfolio_block:sheet_data';

  /**
   * The cache tag invalidated when fresh data is written.
   */
  const TAG = 'oit:portfolio';

  /**
   * The cache TTL in seconds (26 hours).
   */
  const TTL = 93600;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Constructs a new PortfolioData service.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(CacheBackendInterface $cache_backend, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->cacheBackend = $cache_backend;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * Gets the raw sheet data, populating the cache on a cold miss.
   *
   * On a cold miss this performs a one-off live fetch and caches a non-empty
   * result for 26 hours so the table is never empty on a successful fetch. It
   * does not invalidate the cache tag — the render that triggered it caches
   * itself fresh anyway.
   *
   * @return array
   *   The raw fetched sheet data.
   */
  public function getSheetData(): array {
    if ($cached = $this->cacheBackend->get(self::CID)) {
      return $cached->data;
    }

    $data = $this->fetchRaw();
    $this->writeCache($data);
    return $data;
  }

  /**
   * Forces a live fetch and warms the cache.
   *
   * Writes a non-empty result for 26 hours and, when the write actually
   * happened, invalidates the cache tag so any rendered block rebuilds
   * immediately with fresh data. On an empty fetch nothing is written and the
   * tag is not invalidated, leaving the warm data and render cache untouched.
   */
  public function refresh(): void {
    $data = $this->fetchRaw();
    if ($this->writeCache($data)) {
      $this->cacheTagsInvalidator->invalidateTags([self::TAG]);
    }
  }

  /**
   * Performs the live network fetch of the sheet.
   *
   * Isolated as the single network seam so unit tests can override it with
   * canned or empty data.
   *
   * @return array
   *   The raw fetched sheet data.
   */
  protected function fetchRaw(): array {
    return (new GoogleSheetsFetch(self::SHEET_KEY, self::SHEET_GID, 0))->getFetchedSheet();
  }

  /**
   * Writes the data to the cache, guarding against empty results.
   *
   * Never caches an empty result so a transient upstream failure cannot pin an
   * empty table or clobber a good 26h entry. Centralizes the non-empty guard
   * and the 26h TTL so they cannot drift apart between the read and write
   * paths.
   *
   * @param array $data
   *   The data to cache.
   *
   * @return bool
   *   TRUE if the cache was written, FALSE if the data was empty.
   */
  private function writeCache(array $data): bool {
    if (empty($data)) {
      return FALSE;
    }
    $this->cacheBackend->set(self::CID, $data, time() + self::TTL);
    return TRUE;
  }

}
