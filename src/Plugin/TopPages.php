<?php

namespace Drupal\oit\Plugin;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;

/**
 * Plugin to retrieve top pages data from a GitHub-hosted JSON file.
 *
 * @TopPages (
 *   id = "top_pages",
 *   title = @Translation("Top Pages"),
 *   description = @Translation("Get top pages from GH action json.")
 * )
 */
class TopPages {

  /**
   * Host to pull page from.
   *
   * @var string
   */
  private $host;

  /**
   * Iteration.
   *
   * @var int
   */
  private $iteration;

  /**
   * Top pages json.
   *
   * @var array
   */
  private $fullTopPages;

  /**
   * The Teams logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Send teams alert.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  protected $teamsAlert;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TopPages object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $channelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\oit\Plugin\TeamsAlert $teams_alert
   *   The Teams alert service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    LoggerChannelFactoryInterface $channelFactory,
    ConfigFactory $config_factory,
    RequestStack $request_stack,
    ClientInterface $http_client,
    TeamsAlert $teams_alert,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->logger = $channelFactory->get('oit');
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->httpClient = $http_client;
    $this->teamsAlert = $teams_alert;
    $this->entityTypeManager = $entity_type_manager;

    $this->host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    // Log the host for debugging.
    $this->logger->debug('Host set to: ' . $this->host);
  }

  /**
   * Fetch top pages JSON data from the remote source.
   */
  private function fetchData() {
    // Get yesterdays date in the format YYYYMMDD.
    $yesterday = date('Ymd', strtotime('-1 day'));

    try {
      $response = $this->httpClient->request('GET', "https://ucboulder.github.io/oit_dingo/top/$yesterday.json");
      $raw_data = $response->getBody()->getContents();

      // Clean invalid UTF-8 characters.
      $raw_data = mb_convert_encoding($raw_data, 'UTF-8', 'UTF-8');

      $data = json_decode($raw_data, TRUE);
      if ($data === NULL) {
        $json_error = json_last_error_msg();
        $this->logger->error('Could not decode top pages json. Error: ' . $json_error);
        $data = [];
      }
      else {
        $data = $data['requests']['data'] ?? [];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Could not retrieve top pages json: ' . $e->getMessage());
      $data = [];
    }

    $this->fullTopPages = $data;

    $this->whitelistIp();
  }

  /**
   * Whitelist ip to prevent ban.
   */
  public function whitelistIp() {
    // Get current user ip.
    $ip = $this->requestStack->getCurrentRequest()->getClientIp();
    if (empty($ip)) {
      return;
    }
    $autoban_settings = $this->configFactory->getEditable('autoban.settings');
    $whitelist_raw = $autoban_settings->get('autoban_whitelist') ?? '';
    $entries = array_filter(array_map('trim', explode("\n", $whitelist_raw)));
    // Add ip to whitelist if it doesn't already exist (exact match).
    if (!in_array($ip, $entries, TRUE)) {
      $entries[] = $ip;
      $autoban_settings->set('autoban_whitelist', implode("\n", $entries))->save();
      $this->logger->debug('Added ip to whitelist: ' . $ip);
    }
  }

  /**
   * Top Pages List.
   */
  public function getTopPages() {
    $this->fetchData();

    $this->iteration = 0;
    foreach ($this->fullTopPages as $page) {
      $url = $page['data'];
      $url = explode('?', $url)[0];

      if (str_starts_with($url, '/services/')) {
        if ($this->iteration < 9) {
          $title = $this->titleLookup($url);

          // If title does not contain 'Log in'.
          if (str_contains($title, 'Log in') ||
            str_contains($title, 'Federated Identity Service') ||
            str_contains($title, 'Computing Labs')
          ) {
            continue;
          }

          // If Title already exists.
          if (isset($top_pages)) {
            $titles = array_column($top_pages, 'title');
            if (in_array($title, $titles)) {
              continue;
            }
          }

          $top_pages[] = [
            'title' => $title,
            'url' => $url,
          ];

          $this->iteration++;
        }
      }
    }

    if (isset($top_pages)) {
      $this->buildSaveBlock(9, $top_pages, 152, "Top Service Pages", "/services#az", "top-services");
    }

  }

  /**
   * Top Tutorials List.
   */
  public function getTopTutorials() {
    $this->fetchData();

    $this->iteration = 0;

    foreach ($this->fullTopPages as $page) {
      $url = $page['data'];
      $url = explode('?', $url)[0];

      if (str_starts_with($url, '/tutorial/')) {
        if ($this->iteration < 9) {
          $title = $this->titleLookup($url);

          // If title does not contain 'Log in'.
          if (str_contains($title, 'Log in') ||
            str_contains($title, 'Federated Identity Service') ||
            str_contains($title, 'Clear the Mobile Web Browser Cache') ||
            str_contains($title, 'Clear the Web Browser Cache')
          ) {
            continue;
          }

          // If Title already exists.
          if (isset($top_tutorials)) {
            $titles = array_column($top_tutorials, 'title');
            if (in_array($title, $titles)) {
              continue;
            }
          }

          $top_tutorials[] = [
            'title' => $title,
            'url' => $url,
          ];

          $this->iteration++;
        }
      }
    }

    if (isset($top_tutorials)) {
      $this->buildSaveBlock(9, $top_tutorials, 153, "Top Tutorials", "/tutorial/search", "top-tutorials");
    }

  }

  /**
   * Build an HTML list and save it to a block content entity.
   *
   * @param int $count
   *   The expected number of items in the list.
   * @param array $top_pages
   *   Array of page data with 'title' and 'url' keys.
   * @param int $block_id
   *   The block content entity ID to update.
   * @param string $block_title
   *   The heading text for the block.
   * @param string $block_url
   *   The URL the block heading should link to.
   * @param string $block_class
   *   CSS class to apply to the list element.
   */
  private function buildSaveBlock($count, $top_pages, $block_id, $block_title, $block_url, $block_class) {
    if ($top_pages === NULL) {
      $this->logger->debug('Faulty JSON');
      $this->teamsAlert->sendMessage("Front Page - $block_title - Faulty JSON", ['live']);
      return;
    }

    // If top_pages is empty, set to empty array.
    if (count($top_pages) == $count) {
      $top_list_html = "<h2><a href='$block_url'>$block_title</a></h2><ul class='$block_class gray-links force-list-style'>\n";

      foreach ($top_pages as $page) {
        $top_list_html .= '<li class="truncate"><a href="' . Html::escape($page['url']) . '">' . Html::escape($page['title']) . "</a></li>\n";
      }

      $top_list_html .= '</ul>';

      $block = $this->entityTypeManager->getStorage('block_content');
      $tsp_block = $block->load($block_id);
      $tsp_block->body->value = $top_list_html;
      $tsp_block->body->format = 'rich_text';
      $tsp_block->save();

      $this->logger->debug("Fetched $block_title and updated block.");
    }
    else {
      $this->teamsAlert->sendMessage($block_title . ' count set to ' . $count . ', but the returned count is ' . count($top_pages) . ' instead. So no update happened.', ['live']);
      $this->logger->debug($block_title . ' count set to ' . $count . ', but the returned count is ' . count($top_pages) . ' instead. So no update happened.');
    }
  }

  /**
   * Fetch a page and parse its HTML title element.
   *
   * @param string $url
   *   The relative URL of the page to fetch.
   *
   * @return string
   *   The page title, or 'Log in' if the page is inaccessible.
   */
  private function titleLookup($url) {
    $response = $this->httpClient->request('GET', $this->host . $url);
    $response_code = $response->getStatusCode();

    if ($response_code == 200) {
      $page = $response->getBody()->getContents();

      if (preg_match("/<title>(.*?)<\/title>/is", $page, $matches)) {
        $title = $matches[1];
      }
      else {
        $title = '';
      }
      $title = explode(' | ', $title)[0];

      // Make sure no redirect to SSO login.
      if ($response->getHeader('Server')[0] != 'nginx') {
        $title = 'Log in';
      }
    }
    else {
      $title = 'Log in';
    }

    return $title;
  }

}
