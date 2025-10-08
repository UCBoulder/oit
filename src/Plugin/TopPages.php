<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;

/**
 * Environment icon to be used on header title.
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
  private $host = 'https://oit.colorado.edu';

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
   * Sets up to send message to Teams.
   */
  public function __construct(
    LoggerChannelFactoryInterface $channelFactory,
    ConfigFactory $config_factory,
    RequestStack $request_stack,
    ClientInterface $http_client,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->logger = $channelFactory->get('oit');
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;

    // Get yesterdays date in the format YYYYMMDD.
    $yesterday = date('Ymd', strtotime('-1 day'));
    $data = file_get_contents("https://ucboulder.github.io/oit_dingo/top/$yesterday.json");

    if ($data === FALSE) {
      $this->logger->error('Could not retrieve top pages json.');
      $data['requests']['data'] = [];
    }
    else {
      $data = json_decode($data, TRUE);
    }

    $this->fullTopPages = $data['requests']['data'];

    $this->whitelistIp();
  }

  /**
   * Whitelist ip to prevent ban.
   */
  public function whitelistIp() {
    // Get current user ip.
    $ip = $this->requestStack->getCurrentRequest()->getClientIp();
    $autoban_settings = $this->configFactory->getEditable('autoban.settings');
    $whitelist = $autoban_settings->get('autoban_whitelist');
    // Add ip to whitelist if it doesn't already exist.
    if (!str_contains($whitelist, $ip)) {
      $whitelist .= "\n" . $ip;
      $autoban_settings->set('autoban_whitelist', $whitelist)->save();
      $this->logger->debug('Added ip to whitelist: ' . $ip);
    }
  }

  /**
   * Top Pages List.
   */
  public function getTopPages() {
    $this->iteration = 0;
    foreach ($this->fullTopPages as $page) {
      $url = $page['data'];
      $url = explode('?', $url)[0];

      if (str_starts_with($url, '/services/')) {
        if ($this->iteration < 9) {
          $title = $this->titleLookup($url);

          // If title does not contain 'Log in'.
          if (str_contains($title, 'Log in') ||
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

    // If top_pages is empty, set to empty array.
    if (count($top_pages) == 9) {
      $top_list_html = "<ul class='gray-links force-list-style'>\n";

      foreach ($top_pages as $page) {
        $top_list_html .= '<li><a href="' . $page['url'] . '">' . $page['title'] . "</a></li>\n";
      }

      $top_list_html .= '</ul>';

      $tsp_block_id = 152;
      $block = $this->entityTypeManager->getStorage('block_content');
      $tsp_block = $block->load($tsp_block_id);
      $tsp_block->body->value = $top_list_html;
      $tsp_block->body->format = 'rich_text';
      $tsp_block->save();

      $this->logger->debug('Fetched top services pages and updated block.');
    }

  }

  /**
   * Top Tutorials List.
   */
  public function getTopTutorials() {
    $this->iteration = 0;

    foreach ($this->fullTopPages as $page) {
      $url = $page['data'];
      $url = explode('?', $url)[0];

      if (str_starts_with($url, '/tutorial/')) {
        if ($this->iteration < 9) {
          $title = $this->titleLookup($url);

          // If title does not contain 'Log in'.
          if (str_contains($title, 'Log in') ||
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

    // If top_pages is empty, set to empty array.
    if (count($top_tutorials) == 9) {
      $top_list_html = "<ul class='gray-links force-list-style'>\n";

      foreach ($top_tutorials as $page) {
        $top_list_html .= '<li><a href="' . $page['url'] . '">' . $page['title'] . "</a></li>\n";
      }

      $top_list_html .= '</ul>';

      $tsp_block_id = 153;
      $block = $this->entityTypeManager->getStorage('block_content');
      $tsp_block = $block->load($tsp_block_id);
      $tsp_block->body->value = $top_list_html;
      $tsp_block->body->format = 'rich_text';
      $tsp_block->save();

      $this->logger->debug('Fetched top tutorials and updated block.');
    }

  }

  /**
   * Top Tutorials List.
   */
  private function titleLookup($url) {
    $response = $this->httpClient->request('GET', $this->host . $url);
    $response_code = $response->getStatusCode();

    if ($response_code == 200) {
      $page = $response->getBody()->getContents();

      preg_match("/<title>(.*)<\/title>/i", $page, $matches);
      $title = $matches[1];
      $title = explode(' | ', $title)[0];
    }
    else {
      $title = 'Log in';
    }

    return $title;
  }

}
