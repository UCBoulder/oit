<?php

namespace Drupal\oit\Plugin;

use GuzzleHttp\Exception\GuzzleException;
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
   * Store array from CVS.
   *
   * @var array
   */
  private $arrayCvs;

  /**
   * Constructs a new CvsToArray object and parses the CSV from a URL.
   *
   * @param string|null $file
   *   The URL of the CSV to fetch. Local file paths are not supported. An
   *   empty value is treated as a failed fetch.
   * @param string $delimiter
   *   The field delimiter character used in the CSV file.
   */
  public function __construct($file, $delimiter) {
    $this->arrayCvs = [];
    if ($file === NULL || $file === '') {
      static::reportOpenError('', 'No URL provided.');
      return;
    }
    try {
      $body = static::fetch($file);
    }
    catch (GuzzleException $e) {
      static::reportOpenError($file, static::describeFailure($e));
      return;
    }
    // Parse via a memory stream so fgetcsv() keeps handling quoted
    // multi-line fields exactly as it did with a file handle.
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $body);
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
   * Fetches the CSV body over HTTP with bounded timeouts.
   *
   * Placed in a static method so \Drupal calls are permissible for this
   * utility class that cannot use constructor injection.
   *
   * @param string $file
   *   The URL to fetch.
   *
   * @return string
   *   The response body.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Thrown on connection errors, timeouts, and 4xx/5xx responses.
   */
  private static function fetch(string $file): string {
    $response = \Drupal::httpClient()->get($file, [
      'timeout' => 15,
      'connect_timeout' => 5,
    ]);
    return (string) $response->getBody();
  }

  /**
   * Builds a short, human-readable reason for a failed fetch.
   *
   * HTTP error responses are reduced to their status line so response bodies
   * are not copied into logs or Teams alerts.
   *
   * @param \GuzzleHttp\Exception\GuzzleException $e
   *   The exception thrown by the HTTP client.
   *
   * @return string
   *   The failure reason.
   */
  private static function describeFailure(GuzzleException $e): string {
    if ($e instanceof RequestException && $e->hasResponse()) {
      $response = $e->getResponse();
      return trim('HTTP ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
    }
    return $e->getMessage();
  }

  /**
   * Reports a fetch failure via logger, messenger, and Teams alert.
   *
   * Placed in a static method so \Drupal calls are permissible for this
   * utility class that cannot use constructor injection.
   *
   * @param string $file
   *   The URL that could not be fetched (empty string when none).
   * @param string $reason
   *   Why the fetch failed, e.g. a timeout or an HTTP status.
   */
  private static function reportOpenError(string $file, string $reason): void {
    \Drupal::logger('oit')->warning('CvsToArray failed to open: @file. Reason: @reason', [
      '@file' => $file,
      '@reason' => $reason,
    ]);
    \Drupal::messenger()->addError('Could not retrieve Google Sheet data. The sheet may be unavailable or the URL may be invalid.');
    $teams = \Drupal::service('oit.teamsalert');
    $teams->sendMessage('Google Sheet fetch failed (' . $reason . '). URL: ' . $file);
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
