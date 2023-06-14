<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Environment icon to be used on header title.
 *
 * @RedirectAddAnalytics(
 *   id = "redirect_add_analytics",
 *   title = @Translation("Redirect add analytics"),
 *   description = @Translation("Query for redirects and add the proper
 *   utm info. Will run nightly in cron.")
 * )
 */
class RedirectAddAnalytics {

  /**
   * Run Database query.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The RedirectAddAnalytics logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Look for redirects missing utm code and add it.
   */
  public function __construct(
    Connection $connection,
    LoggerChannelFactoryInterface $channelFactory
  ) {
    $this->connection = $connection;
    $this->logger = $channelFactory->get('oit');
    $query = $this->connection->select('redirect', 'r');
    $query->fields('r', [
      'rid',
      'redirect_source__path',
      'redirect_redirect__uri',
    ]);
    $query->condition('redirect_redirect__uri', '%?utm%', 'NOT LIKE');
    $query->range(0, 500);
    $redirects = $query->execute()->fetchAll();
    foreach ($redirects as $redirect) {
      $uri = $redirect->redirect_redirect__uri;
      $path = $redirect->redirect_source__path;
      $query = $this->connection->update('redirect');
      $query->condition('rid', $redirect->rid);
      $query->fields([
        'redirect_redirect__uri' => $uri .
        '?utm_source=' .
        $path .
        '&utm_campaign=redirect',
      ]);
      $query->execute();
    }
    $this->logger->notice('Redirects updated with utm info.');
  }

}
