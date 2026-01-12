<?php

namespace Drupal\oit\Services;

/**
 * Manages cron job callbacks for the OIT module.
 */
class CronManager {

  /**
   * Adds analytics redirects via the oit.redirect.add.analytics service.
   */
  public static function addAnalyticsRedirects() {
    \Drupal::service('oit.redirect.add.analytics');
  }

  /**
   * Teams message for banned ips and check against abuse database.
   */
  public static function autoBan() {
    // Send message when new banned ip is added.
    $latest_ban = \Drupal::service('oit.latestAutoBan');
    $current_ban_id = $latest_ban->latestBanId;
    $last_ban_id = $latest_ban->lastBanId;
    if ($current_ban_id != $last_ban_id) {
      $latest_ban->messageLatestIps();
    }
  }

  /**
   * Archive Old News.
   */
  public static function archiveNews() {
    // Archive news older than 90 days.
    $cut_off = strtotime("-90 days");
    $archivenews = \Drupal::service('oit.archivenews');
    $archivenews->archive($cut_off);
  }

  /**
   * Archive Service Alerts categorized as service maintenance.
   *
   * Archives service alerts that are past their completion date.
   */
  public static function archiveServiceMaintenance() {
    \Drupal::service('oit.smc');
  }

  /**
   * Update Top Services block.
   */
  public static function topServices() {
    $env = getenv('PANTHEON_ENVIRONMENT');

    if ($env === 'live') {
      \Drupal::service('oit.toppages')->getTopPages();
    }
    else {
      \Drupal::logger('oit')->notice('Top Services not run. This is the @env environment', ['@env' => $env]);
    }
  }

  /**
   * Update Top tutorials block.
   */
  public static function topTutorials() {
    $env = getenv('PANTHEON_ENVIRONMENT');

    if ($env === 'live') {
      \Drupal::service('oit.toppages')->getTopTutorials();
    }
    else {
      \Drupal::logger('oit')->notice('Top Tutorials not run. This is the @env environment', ['@env' => $env]);
    }
  }

}
