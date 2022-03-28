<?php

namespace Drupal\oit\Plugin;

/**
 * Provides ability to pull data from google sheets.
 *
 * @GoogleSheetsTopLinks (
 *   id = "googlesheetstoplinks",
 *   title = @Translation("Google Sheets Top Links"),
 *   description = @Translation("Sorts out top links")
 * )
 */
class GoogleSheetsTopLinks {
  /**
   * Sheets data.
   *
   * @var string
   */
  private $topLinksData;

  /**
   * Process top links from google sheet.
   */
  public function __construct($key, $gid, $fields, $shift, $links_returned) {
    $fetchData = new GoogleSheetsFetch($key, $gid, $shift);
    $gsheet_returned_data = $fetchData->getFetchedSheet();
    $processData = new GoogleSheetsProcess($gsheet_returned_data, $fields, 'custom');
    $data = $processData->getProcessedData();
    $top_pages = "<ul class='top-links gray-links force-list-style'>\n";
    if (isset($data['rows'])) {
      $n = 0;
      foreach ($data['rows'] as $row) {
        if ($n == $links_returned) {
          break;
        }
        $title = strip_tags($row['data'][0]);
        $title = preg_replace("/\| Office of Information Technology ?-? ?O?I?T?/", "", $title);
        $title = preg_replace("/🔴🐕 /", "", $title);
        $href = strip_tags($row['data'][1]);
        $top_pages .= sprintf(
          "<li class='truncate'><a href='%s' title='%s'>%s</a></li>\n",
          $href,
          $title,
          $title
        );
        $n++;
      }
      $top_pages .= "</ul>";
      $this->topLinksData = $top_pages;
    }
  }

  /**
   * Return sheet data.
   */
  public function getTopLinksData() {
    return $this->topLinksData;
  }

}
