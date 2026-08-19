<?php

namespace Drupal\oit\Plugin;

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
   * Constructs a new CvsToArray object and parses the CSV file.
   *
   * @param string $file
   *   The path or URL of the CSV file to open.
   * @param string $delimiter
   *   The field delimiter character used in the CSV file.
   */
  public function __construct($file, $delimiter) {
    $this->arrayCvs = [];
    $handle = @fopen($file, 'r');
    if ($handle !== FALSE) {
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
    else {
      static::reportOpenError($file);
    }
  }

  /**
   * Reports a file-open failure via logger, messenger, and Teams alert.
   *
   * Placed in a static method so \Drupal calls are permissible for this
   * utility class that cannot use constructor injection.
   *
   * @param string $file
   *   The file path or URL that could not be opened.
   */
  private static function reportOpenError(string $file): void {
    \Drupal::logger('oit')->warning('CvsToArray failed to open: @file', ['@file' => $file]);
    \Drupal::messenger()->addError('Could not retrieve Google Sheet data. The sheet may be unavailable or the URL may be invalid.');
    $teams = \Drupal::service('oit.teamsalert');
    $teams->sendMessage('Google Sheet fetch failed (HTTP error). URL: ' . $file);
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
