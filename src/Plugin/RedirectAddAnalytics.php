<?php

namespace Drupal\oit\Plugin;

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
   * Look for redirects missing utm code and add it.
   */
  public function __construct() {
    $query = \Drupal::database()->select('redirect', 'r');
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
      $query = \Drupal::database()->update('redirect');
      $query->condition('rid', $redirect->rid);
      $query->fields([
        'redirect_redirect__uri' => $uri .
        '?utm_source=' .
        $path .
        '&utm_campaign=redirect',
      ]);
      $query->execute();
    }
    \Drupal::logger('oit')->notice('Redirects updated with utm info.');
  }

}
