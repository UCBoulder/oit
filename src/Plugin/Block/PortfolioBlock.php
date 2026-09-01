<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
          $cells = $row['data'];
          $description = $cells[3] ?? '';
          $customerbenefit = $cells[4] ?? '';
          $stats = [
            '#type' => 'inline_template',
            '#template' => '<strong>{{ priority_label }}</strong> {{ priority }}' .
            '<strong>{{ start_label }}</strong> {{ start }}' .
            '<strong>{{ percent_label }}</strong> {{ percent }}' .
            '<strong>{{ status_label }}</strong> {{ status }}',
            '#context' => [
              'priority_label' => $this->t('Priority'),
              'priority' => $cells[0],
              'start_label' => $this->t('Start'),
              'start' => $cells[5],
              'percent_label' => $this->t('Percent Complete'),
              'percent' => $cells[6],
              'status_label' => $this->t('Status Name'),
              'status' => $cells[9],
            ],
          ];
          $open = $n == 0 ? 'open' : '';
          $project = [];
          if (!empty($description)) {
            $project[] = $this->detailsSection($open, $this->t('Description'), $description);
          }
          if (!empty($customerbenefit)) {
            $project[] = $this->detailsSection($open, $this->t('Customer Benefit'), $customerbenefit);
          }
          $rows[] = [
            'name' => [
              'data' => $cells[1],
            ],
            'stats' => [
              'data' => $stats,
            ],
            'manager' => [
              'data' => $cells[2],
            ],
            'project' => [
              'data' => $project,
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
        'library' => ['oit/table_search', 'oit/oit_projects', 'oit/oit_portfolio'],
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

  /**
   * Builds a collapsible details section for a portfolio table cell.
   *
   * @param string $open
   *   The 'open' attribute value, or an empty string for collapsed.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The summary label.
   * @param array|string $body
   *   The renderable body of the details section.
   *
   * @return array
   *   An inline_template render array.
   */
  protected function detailsSection($open, TranslatableMarkup $label, $body) {
    return [
      '#type' => 'inline_template',
      '#template' => '<details {{ open }} class="no-deets-controls"><summary>{{ label }}</summary><p>{{ body }}</p></details>',
      '#context' => [
        'open' => $open,
        'label' => $label,
        'body' => $body,
      ],
    ];
  }

}
