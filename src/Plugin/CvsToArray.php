<?php

namespace Drupal\oit\Plugin;

use GuzzleHttp\Exception\RequestException;

/**
 * CVS from google to associative array.
 *
 * @CvsToArray (
 *   id = "cvstoarray",
 *   title = @Translation("CVS to array"),
 *   description = @Translation("Convert cvs layout to array")
 * )
 */
class CvsToArray {

  /**
   * Number of times a fetch is attempted before it is considered failed.
   *
   * The published Google Sheets CSV endpoint throttles and returns transient
   * 5xx responses, so a single blip should not fail the whole fetch.
   */
  const MAX_ATTEMPTS = 3;

  /**
   * Seconds to wait between fetch attempts, indexed by attempt number.
   */
  const RETRY_DELAYS = [1, 2];

  /**
   * Consecutive failures for one URL before a Teams alert is sent.
   */
  const ALERT_THRESHOLD = 3;

  /**
   * Prefix of the state key holding the consecutive failure count per URL.
   */
  const FAILURE_STATE_PREFIX = 'oit.gsheet_failures.';

  /**
   * Store array from CVS.
   *
   * @var array
   */
  private $arrayCvs;

  /**
   * Whether the remote sheet was retrieved successfully.
   *
   * @var bool
   */
  private $fetchSucceeded = FALSE;

  /**
   * Constructs a new CvsToArray object and parses the CSV file.
   *
   * @param string $file
   *   The path or URL of the CSV file to open.
   * @param string $delimiter
   *   The field delimiter character used in the CSV file.
   */
  public function __construct($file, $delimiter) {
    $this->arrayCvs = [];
    $csv = static::fetchCsv($file);
    if ($csv === NULL) {
      return;
    }
    $this->fetchSucceeded = TRUE;
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);
    $i = 0;
    $arr = [];
    while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"', '')) !== FALSE) {
      for ($j = 0; $j < count($lineArray); $j++) {
        $arr[$i][$j] = $lineArray[$j];
      }
      $i++;
    }
    fclose($handle);
    $this->arrayCvs = $arr;
  }

  /**
   * Retrieves the CSV body, retrying transient failures.
   *
   * Uses the http_client so connect and read timeouts are bounded: an
   * unresponsive Google can otherwise hold a cron run or an editor's entity
   * save open for the full default_socket_timeout.
   *
   * @param string $file
   *   The file path or URL to retrieve.
   *
   * @return string|null
   *   The CSV body, or NULL when every attempt failed.
   */
  private static function fetchCsv(string $file): ?string {
    $client = \Drupal::httpClient();
    $error = '';
    for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
      try {
        $response = $client->get($file, [
          'connect_timeout' => 10,
          'timeout' => 20,
          'headers' => [
            'User-Agent' => 'Drupal OIT Google Sheet fetch',
          ],
        ]);
        $body = (string) $response->getBody();
        if (trim($body) === '') {
          // A 200 with nothing in it is not usable data; treat it as a
          // failure so the caller keeps whatever it already had.
          throw new \RuntimeException('Empty response body.');
        }
        static::clearFailureCount($file);
        return $body;
      }
      catch (RequestException $e) {
        $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
        $error = $status ? 'HTTP ' . $status : $e->getMessage();
      }
      catch (\Throwable $e) {
        $error = $e->getMessage();
      }
      if ($attempt < self::MAX_ATTEMPTS) {
        sleep(self::RETRY_DELAYS[$attempt - 1] ?? 1);
      }
    }
    static::reportOpenError($file, $error);
    return NULL;
  }

  /**
   * Reports a fetch failure via logger, messenger, and Teams alert.
   *
   * Placed in a static method so \Drupal calls are permissible for this
   * utility class that cannot use constructor injection.
   *
   * The Teams alert only fires once the same URL has failed
   * self::ALERT_THRESHOLD times in a row, so a short Google outage does not
   * flood the channel. The watchdog entry is written on every failure.
   *
   * @param string $file
   *   The file path or URL that could not be retrieved.
   * @param string $error
   *   The error from the final attempt.
   */
  private static function reportOpenError(string $file, string $error): void {
    $failures = static::incrementFailureCount($file);
    \Drupal::logger('oit')->warning('CvsToArray failed to fetch @file after @attempts attempts (@error). Consecutive failures: @failures', [
      '@file' => $file,
      '@attempts' => self::MAX_ATTEMPTS,
      '@error' => $error,
      '@failures' => $failures,
    ]);
    \Drupal::messenger()->addError('Could not retrieve Google Sheet data. The sheet may be unavailable or the URL may be invalid.');
    if ($failures === self::ALERT_THRESHOLD) {
      $teams = \Drupal::service('oit.teamsalert');
      $teams->sendMessage(sprintf(
        'Google Sheet fetch failed %d times in a row (%s). URL: %s',
        $failures,
        $error,
        $file
      ));
    }
  }

  /**
   * Builds the state key holding the failure count for a URL.
   *
   * @param string $file
   *   The file path or URL.
   *
   * @return string
   *   The state key.
   */
  private static function failureStateKey(string $file): string {
    return self::FAILURE_STATE_PREFIX . md5($file);
  }

  /**
   * Records another consecutive failure for a URL.
   *
   * @param string $file
   *   The file path or URL.
   *
   * @return int
   *   The new consecutive failure count.
   */
  private static function incrementFailureCount(string $file): int {
    $state = \Drupal::state();
    $key = static::failureStateKey($file);
    $failures = (int) $state->get($key, 0) + 1;
    $state->set($key, $failures);
    return $failures;
  }

  /**
   * Clears the failure count for a URL after a successful fetch.
   *
   * @param string $file
   *   The file path or URL.
   */
  private static function clearFailureCount(string $file): void {
    \Drupal::state()->delete(static::failureStateKey($file));
  }

  /**
   * Whether the sheet was retrieved successfully.
   *
   * @return bool
   *   TRUE when the remote fetch succeeded.
   */
  public function isSuccessful(): bool {
    return $this->fetchSucceeded;
  }

  /**
   * Return the parsed CSV data as an array.
   *
   * @return array
   *   The parsed CSV data.
   */
  public function getBuiltArray() {
    return $this->arrayCvs;
  }

}
