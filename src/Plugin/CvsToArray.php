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
    if (($handle = fopen($file, 'r')) !== FALSE) {
      $i = 0;
      while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE) {
        for ($j = 0; $j < count($lineArray); $j++) {
          $arr[$i][$j] = $lineArray[$j];
        }
        $i++;
      }
      fclose($handle);
      $this->arrayCvs = $arr;
    }
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
