<?php

namespace Drupal\oit\Plugin;

use Drupal\Component\Utility\Xss;

/**
 * Process google sheets data and spits out array.
 *
 * @GoogleSheetsApi(
 *   id = "googlesheetsprocess",
 *   title = @Translation("Google Sheets Processor"),
 *   description = @Translation("Proccesses google sheets data")
 * )
 */
class GoogleSheetsProcess {
  /**
   * Return processed data.
   *
   * @var array
   */
  private $processedData;

  /**
   * Process google sheet.
   */
  public function __construct($gsheet_returned_data, $sheet_letters, $process = 'ss') {
    $sheet_letters = strtolower($sheet_letters);
    $sheet_letters = str_replace(' ', '', $sheet_letters);
    $sheet_letters = explode(',', Xss::filter($sheet_letters));
    $i = 0;
    $alphabet = [
      'a' => 0,
      'b' => 1,
      'c' => 2,
      'd' => 3,
      'e' => 4,
      'f' => 5,
      'g' => 6,
      'h' => 7,
      'i' => 8,
      'j' => 9,
      'k' => 10,
      'l' => 11,
      'm' => 12,
      'n' => 13,
      'o' => 14,
      'p' => 15,
      'q' => 16,
      'r' => 17,
      's' => 18,
      't' => 19,
      'u' => 20,
      'v' => 21,
      'w' => 22,
      'x' => 23,
      'y' => 25,
      'z' => 26,
    ];
    foreach ($sheet_letters as $sheet_letter) {
      $sheet_items[] = $alphabet[$sheet_letter];
    }
    // @todo looking for $process but there may be a better way to clean this up
    // later. Fix some day.
    if ($process == 'custom') {
      foreach ($gsheet_returned_data[0] as $key => $value) {
        $sheet_header[] = $key;
        $i++;
      }
      foreach ($sheet_items as $value) {
        $headers[] = $sheet_header[$value];
      }

      $format = "rich_text";
      foreach ($gsheet_returned_data as $key => $value) {
        foreach ($headers as $key => $header) {
          $item[$key] = isset($value[$header]) ? check_markup($value[$header], $format) : '';
        }
        $rows[] = [
          'data' => $item,
        ];
      }
    }
    else {
      foreach ($gsheet_returned_data[0] as $key => $value) {
        $sheet_header[] = $value;
        $i++;
      }
      foreach ($sheet_items as $value) {
        $headers[] = $sheet_header[$value];
      }

      $format = "rich_text";
      $rows_exist = isset($gsheet_returned_data[1]) ? TRUE : FALSE;
      if ($rows_exist) {
        foreach ($gsheet_returned_data as $key => $value) {
          // Skip first header row.
          if ($key != 0) {
            foreach ($sheet_items as $key => $header) {
              $item[$key]['data']['#markup'] = isset($value[$header]) ? check_markup($value[$header], $format) : '';
            }
            $rows[] = $item;
          }
        }
      }
      else {
        // Be sure not to submit empty rows.
        $rows[] = $headers;
      }
    }
    $data['rows'] = $rows;
    $data['header'] = $headers;
    $this->processedData = $data;
  }

  /**
   * Return processed google sheet.
   */
  public function getProcessedData() {
    return $this->processedData;
  }

}
