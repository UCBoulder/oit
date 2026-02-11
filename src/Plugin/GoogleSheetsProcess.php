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
    // Validate input data.
    if (!is_array($gsheet_returned_data) || empty($gsheet_returned_data)) {
      $this->processedData = ['rows' => [], 'header' => []];
      return;
    }

    // Validate and sanitize process parameter.
    $allowed_processes = ['ss', 'custom'];
    $process = in_array($process, $allowed_processes, TRUE) ? $process : 'ss';

    // Validate sheet_letters is a string before processing.
    // If not a string (e.g., NULL, array), default to empty string
    // which results in no columns being selected for processing.
    if (!is_string($sheet_letters)) {
      $sheet_letters = '';
    }

    // Sanitize sheet letters input.
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
      'y' => 24,
      'z' => 25,
    ];
    $sheet_items = [];
    foreach ($sheet_letters as $sheet_letter) {
      if (isset($alphabet[$sheet_letter])) {
        $sheet_items[] = $alphabet[$sheet_letter];
      }
    }
    if ($process === 'custom') {
      $sheet_header = [];
      foreach ($gsheet_returned_data[0] as $key => $value) {
        $sheet_header[] = $key;
        $i++;
      }
      $headers = [];
      foreach ($sheet_items as $value) {
        if (isset($sheet_header[$value])) {
          $headers[] = $sheet_header[$value];
        }
      }

      $format = "markdown";
      $rows = [];
      foreach ($gsheet_returned_data as $key => $value) {
        $item = [];
        foreach ($headers as $key => $header) {
          // Sanitize data from external spreadsheet.
          $raw_value = $value[$header] ?? '';
          $item[$key] = check_markup(Xss::filter($raw_value), $format);
        }
        $rows[] = [
          'data' => $item,
        ];
      }
    }
    else {
      $sheet_header = [];
      foreach ($gsheet_returned_data[0] as $key => $value) {
        $sheet_header[] = $value;
        $i++;
      }
      $headers = [];
      foreach ($sheet_items as $value) {
        if (isset($sheet_header[$value])) {
          $headers[] = $sheet_header[$value];
        }
      }

      $format = "markdown";
      $rows = [];
      $rows_exist = isset($gsheet_returned_data[1]) ? TRUE : FALSE;
      if ($rows_exist) {
        foreach ($gsheet_returned_data as $key => $value) {
          // Skip first header row.
          if ($key !== 0) {
            $item = [];
            foreach ($sheet_items as $key => $header) {
              // Sanitize data from external spreadsheet.
              $raw_value = $value[$header] ?? '';
              $item[$key]['data']['#markup'] = check_markup(Xss::filter($raw_value), $format);
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
