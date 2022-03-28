<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\Xss;
use Drupal\oit\Plugin\GoogleSheetsFetch;
use Drupal\oit\Plugin\GoogleSheetsProcess;

/**
 * OIT Projects Portfolio block.
 *
 * @Block(
 *   id = "Portfolio Block",
 *   admin_label = @Translation("Google Sheet listing OIT Project Portfolio
 *   Report")
 * )
 */
class PortfolioBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $portfolio = $this->fetchPortfolio();

    return $portfolio;
  }

  /**
   * Fetch portfolio from firebase.
   */
  public function fetchPortfolio() {
    $fetchData = new GoogleSheetsFetch('1k4-Csp29uLZbh_g2nhuhpq3dVBgZ6zWFK20BXP1rL_s', 0, 0);
    $gsheet_returned_data = $fetchData->getFetchedSheet();
    $processData = new GoogleSheetsProcess($gsheet_returned_data, 'a,b,c,d,e,f,g,h,i,j', 'custom');
    $data = $processData->getProcessedData();
    $header = [
      'Name',
      'Stats',
      'Manager',
      'Project Overview',
    ];
    if (isset($data['rows'])) {
      $n = 0;
      foreach ($data['rows'] as $row) {
        $n++;
        if ($n != 1) {
          $oitpriority = Xss::filter($row['data'][0]);
          $name = Xss::filter($row['data'][1]);
          $manager = Xss::filter($row['data'][2]);
          $description = !empty($row['data'][3]) ? Xss::filter($row['data'][3]) : '';
          $customerbenefit = !empty($row['data'][4]) ? Xss::filter($row['data'][4]) : '';
          $start = Xss::filter($row['data'][5]);
          $percentcomplete = Xss::filter($row['data'][6]);
          $statusname = Xss::filter($row['data'][9]);
          $stats = sprintf(
            '<strong>%s</strong><br /> %s<br/><strong>%s</strong><br /> %s<br/><strong>%s</strong><br /> %s<br/><strong>%s</strong><br /> %s<br/>',
            $this->t('Priority'),
            $oitpriority,
            $this->t('Start'),
            $start,
            $this->t('Percent Complete'),
            $percentcomplete,
            $this->t('Status Name'),
            $statusname
          );
          $open = $n == 0 ? 'open' : '';
          if (!empty($description)) {
            $project = sprintf(
              '<details %s class="no-deets-controls"><summary>%s</summary><p>%s</p></details>',
              $open,
              $this->t('Description'),
              $description
            );
          }
          if (!empty($customerbenefit)) {
            $project .= sprintf(
              '<details %s class="no-deets-controls"><summary>%s</summary><p>%s</p></details>',
              $open,
              $this->t('Customer Benefit'),
              $customerbenefit
            );
          }
          $rows[] = [
            'name' => [
              'data' => [
                '#markup' => $name,
              ],
            ],
            'stats' => [
              'data' => [
                '#markup' => $stats,
              ],
            ],
            'manager' => [
              'data' => [
                '#markup' => $manager,
              ],
            ],
            'project' => [
              'data' => [
                '#markup' => $project,
              ],
            ],
          ];
        }
      }
    }
    $html['report'] = [
      '#theme' => 'table',
      '#sticky' => TRUE,
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['id' => 'gdoc-table', 'class' => ['table-search']],
      '#attached' => [
        'library' => ['oit/table_search', 'oit/oit_projects'],
      ],
    ];
    return $html;
  }

}
