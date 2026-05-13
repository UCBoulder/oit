<?php

namespace Drupal\oit\Plugin;

use Drupal\Component\Utility\Xss;

/**
 * Fetches data from google sheets.
 *
 * @GoogleSheetsFetch(
 *   id = "googlesheetsfetch",
 *   title = @Translation("Google Sheets Fetch"),
 *   description = @Translation("Pulls google sheet data")
 * )
 */
class GoogleSheetsFetch {
  /**
   * Fetch google sheet data.
   *
   * @var string
   */
  private $fetchData;

  /**
   * Sheet count returned.
   *
   * @var string
   */
  private $sheetCount;

  /**
   * Get returned sheet.
   *
   * @var string
   */
  private $cvsSheet;

  /**
   * Constructs a new GoogleSheetsFetch object and fetches the sheet data.
   *
   * @param string $key
   *   The Google Sheets document key or full URL when $shentity is TRUE.
   * @param int $gid
   *   The sheet GID (tab index).
   * @param int $shift
   *   Number of leading rows to remove from the data.
   * @param bool $shentity
   *   When TRUE, treat $key as a full Google Sheets URL.
   */
  public function __construct($key, $gid, $shift = 0, $shentity = FALSE) {
    if ($shentity) {
      // Check the url starts with 'https://docs.google.com'.
      $feed = !empty($key) && strpos($key, 'https://docs.google.com') === 0 ? $key : NULL;
    }
    else {
      $key = !empty($key) ? Xss::filter($key) : NULL;
      $gid = ctype_digit((string) $gid) && (int) $gid >= 0 ? (int) $gid : NULL;
      // See https://gist.github.com/pamelafox/770584
      $feed = "https://docs.google.com/spreadsheets/d/$key/pub?gid=$gid&single=true&output=csv";
    }
    // Arrays we'll use later.
    $newArray = [];
    // Do it.
    $this->cvsSheet = new CvsToArray($feed, ',');
    $data = $this->cvsSheet->getBuiltArray();
    if ($shift) {
      $count = 1;
      while ($count <= $shift) :
        array_shift($data);
        $count++;
      endwhile;
      $newArray = $data;
    }
    else {
      $newArray = $data;
    }
    $this->fetchData = $newArray;
  }

  /**
   * Get sheet that was fetched.
   *
   * @return array
   *   The fetched sheet data as a two-dimensional array.
   */
  public function getFetchedSheet() {
    return $this->fetchData;
  }

  /**
   * Get the result count.
   *
   * @return int
   *   The number of rows in the fetched sheet.
   */
  public function getCount() {
    return $this->sheetCount;
  }

}
