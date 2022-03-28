<?php

namespace Drupal\oit\Plugin;

/**
 * Provides ability to pull data from google sheets.
 *
 * @GoogleSheetsApi(
 *   id = "googlesheetsapi",
 *   title = @Translation("Google Sheets Api"),
 *   description = @Translation("Pulls google sheet data")
 * )
 */
class GoogleSheetsApi {
  /**
   * Sheets data.
   *
   * @var string
   */
  private $sheetData;
  /**
   * Raw CVS sheet data.
   *
   * @var string
   */
  private $cvsSheet;

  /**
   * Grabbing the sheet data.
   */
  public function sheetDefined($key, $sheet_letters, $gid = 0, $shift = 0) {
    $fetchData = new GoogleSheetsFetch($key, $gid, $shift);
    $newArray = $fetchData->getFetchedSheet();
    $processData = new GoogleSheetsProcess($newArray, $sheet_letters);
    $gSheetData = $processData->getProcessedData();

    $this->sheetData = $gSheetData;
    return $this->sheetData;
  }

  /**
   * Return sheet data.
   */
  public function getSheetData() {
    return $this->sheetData;
  }

}
