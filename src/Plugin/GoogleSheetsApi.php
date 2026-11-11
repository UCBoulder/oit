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
   * @var array<string, mixed>|null
   */
  private $sheetData;

  /**
   * Fetch and process a Google Sheet by key and column letters.
   *
   * @param string $key
   *   The Google Sheets document key.
   * @param string $sheet_letters
   *   Comma-separated column letters to include.
   * @param int $gid
   *   The sheet GID (tab index).
   * @param int $shift
   *   Number of header rows to skip.
   * @param bool $shentity
   *   Whether to treat the key as a full URL.
   *
   * @return array<string, mixed>
   *   The processed sheet data.
   */
  public function sheetDefined($key, $sheet_letters, $gid = 0, $shift = 0, $shentity = FALSE) {
    $fetchData = new GoogleSheetsFetch($key, $gid, $shift, $shentity);
    $newArray = $fetchData->getFetchedSheet();
    $processData = new GoogleSheetsProcess($newArray, $sheet_letters);
    $gSheetData = $processData->getProcessedData();

    $this->sheetData = $gSheetData;
    return $this->sheetData;
  }

  /**
   * Return the processed sheet data.
   *
   * @return array<string, mixed>|null
   *   The processed sheet data, or NULL if no sheet has been fetched yet.
   */
  public function getSheetData() {
    return $this->sheetData;
  }

}
