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
   * Archive Service Alerts categorized as service maintenance - past
   * completion date.
   */
  public static function archiveServiceMaintenance() {
    \Drupal::service('oit.smc');
  }

  /**
   * Update Top Services block.
   */
  public static function topServices() {
    \Drupal::service('oit.toppages')->getTopPages();
  }

  /**
   * Update Top tutorials block.
   */
  public static function topTutorials() {
    \Drupal::service('oit.toppages')->getTopTutorials();
  }

}
