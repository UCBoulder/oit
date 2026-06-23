<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oit\Plugin\GoogleSheetsProcess;
use Drupal\oit\Services\PortfolioData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OIT Projects Portfolio block.
 *
 * @Block(
 *   id = "Portfolio Block",
 *   admin_label = @Translation("Google Sheet listing OIT Project Portfolio
 *   Report")
 * )
 */
class PortfolioBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The portfolio data service.
   *
   * @var \Drupal\oit\Services\PortfolioData
   */
  protected $portfolioData;

  /**
   * Constructs a new PortfolioBlock.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\oit\Services\PortfolioData $portfolio_data
   *   The portfolio data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PortfolioData $portfolio_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->portfolioData = $portfolio_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oit.portfolio')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $portfolio = $this->fetchPortfolio();

    return $portfolio;
  }

  /**
   * Fetch portfolio data from Google Sheets and build a render array.
   *
   * @return array
   *   Render array for the portfolio table.
   */
  public function fetchPortfolio() {
    // The raw sheet data is fetched and cached by the oit.portfolio service so
    // the slow remote request happens off the request-render path (warmed by
    // cron) rather than on every render-cache miss.
    $gsheet_returned_data = $this->portfolioData->getSheetData();
    $processData = new GoogleSheetsProcess($gsheet_returned_data, 'a,b,c,d,e,f,g,h,i,j', 'custom');
    $data = $processData->getProcessedData();
    $header = [
      'Name',
      'Stats',
      'Manager',
      'Project Overview',
    ];
    $rows = [];
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
          $project = '';
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
    // Set a single top-level cache entry so the tag bubbles to the node page
    // render cache, the anonymous Internal Page Cache and the Pantheon Global
    // CDN. The max-age is a freshness backstop only — the remote fetch is gated
    // behind the oit.portfolio data cache, not this value.
    $html['#cache'] = [
      'tags' => [PortfolioData::TAG],
      'max-age' => PortfolioData::TTL,
    ];
    return $html;
  }

}
