<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\oit\Plugin\GoogleSheetsProcess;
use Drupal\oit\Plugin\GoogleSheetsFetch;
use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\Xss;

/**
 * Top pages block.
 *
 * @Block(
 *   id = "liaisons_block",
 *   admin_label = @Translation("Google Sheet with all liaisons")
 * )
 */
class LiaisonsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $liaisons = $this->liaisonTable('0AgLrphpbBv94dDVtY05oRUFmd3NpZ19DeVFnWjA5dEE', 0, 'b,c,d,e,f,g,h,i,j,k,l', NULL);

    return $liaisons;
  }

  /**
   * Build Liaison table.
   */
  private function liaisonTable($key, $gid, $fields, $shift) {
    $fetchData = new GoogleSheetsFetch($key, $gid, $shift);
    $gsheet_returned_data = $fetchData->getFetchedSheet();
    $processData = new GoogleSheetsProcess($gsheet_returned_data, $fields, 'custom');
    $data = $processData->getProcessedData();
    $header = [
      'Name',
      'Department',
      'Job Title',
      'Contact Information',
    ];
    foreach ($data['rows'] as $key => $row) {
      if (method_exists($row['data'][3], '__toString') && method_exists($row['data'][6], '__toString') && method_exists($row['data'][8], '__toString')) {
        $last = $this->removeFormat($row['data'][0]);
        $first = $this->removeFormat($row['data'][1]);
        $phone = $this->removeFormat($row['data'][6]);
        if (isset($phone)) {
          $phone_leng = strlen($phone);
          if ($phone_leng == 5) {
            $phone = substr($phone, 0, 1) . '-' . substr($phone, 1);
          }
        }
        $dept = Xss::filter($row['data'][4]);
        $email = $this->removeFormat($row['data'][2]);
        $title = Xss::filter($row['data'][3]->__toString());
        $publish = $this->removeFormat($row['data'][8]->__toString());
        if ($publish == 'Yes') {
          $rows[] = [
            'name' => [
              'data' => $last . ', ' . $first,
            ],
            'dept' => [
              'data' => [
                '#markup' => $dept,
              ],
            ],
            'title' => [
              'data' => [
                '#markup' => $title,
              ],
            ],
            'email' => [
              'data' => [
                '#markup' => '<a href="mailto:' . $email . '">' . $email . '</a> <br />' . $phone,
              ],
            ],
          ];
        }
      }
    }
    $html['itp'] = [
      '#theme' => 'table',
      '#sticky' => TRUE,
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['id' => 'gdoc-table', 'class' => ['table-search']],
      '#attached' => [
        'library' => ['oit/table_search'],
      ],
    ];
    return $html;
  }

  /**
   * Clean string of text.
   */
  private function removeFormat($text) {
    $strip = strip_tags($text);
    $clean = preg_replace('/\s+/', '', $strip);
    return $clean;
  }

}
