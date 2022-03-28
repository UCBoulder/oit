<?php

namespace Drupal\oit\Plugin;

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
   * Fetch google sheet.
   */
  public function __construct($key, $gid, $shift = 0) {
    // See https://gist.github.com/pamelafox/770584
    $feed = "https://docs.google.com/spreadsheets/d/$key/pub?gid=$gid&single=true&output=csv";
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
   */
  public function getFetchedSheet() {
    return $this->fetchData;
  }

  /**
   * Get the result count.
   */
  public function getCount() {
    return $this->sheetCount;
  }

}
