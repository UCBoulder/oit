<?php

namespace Drupal\oit\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\DrushCommands;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\oit\Plugin\TopPages;
use Drupal\Core\Database\Connection;
use Drupal\oit\Plugin\Util\DeleteNews;
use Drupal\oit\Plugin\Util\UserClean;

/**
 * Various utility commands for OIT.
 */
class OitCommands extends DrushCommands {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Teams Alert.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  protected $teamsAlert;

  /**
   * The Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * User Clean.
   *
   * @var \Drupal\oit\Plugin\Util\UserClean
   */
  protected $userClean;

  /**
   * Top Pages.
   *
   * @var \Drupal\oit\Plugin\TopPages
   */
  protected $topPages;

  /**
   * Delete News.
   *
   * @var \Drupal\oit\Plugin\Util\DeleteNews
   */
  protected $deleteNews;

  /**
   * Constructs a new OitCommands object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\oit\Plugin\TeamsAlert $teams_alert
   *   The Teams alert service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\oit\Plugin\Util\UserClean $user_clean
   *   The user clean service.
   * @param \Drupal\oit\Plugin\TopPages $top_pages
   *   The top pages service.
   * @param \Drupal\oit\Plugin\Util\DeleteNews $delete_news
   *   The delete news service.
   */
  public function __construct(
    MessengerInterface $messenger,
    TeamsAlert $teams_alert,
    Connection $database,
    UserClean $user_clean,
    TopPages $top_pages,
    DeleteNews $delete_news,
  ) {
    parent::__construct();
    $this->messenger = $messenger;
    $this->teamsAlert = $teams_alert;
    $this->database = $database;
    $this->userClean = $user_clean;
    $this->topPages = $top_pages;
    $this->deleteNews = $delete_news;
  }

  /**
   * Send Teams Alert.
   *
   * @param string $userMessage
   *   The message to send.
   *
   * @command oit:send-teams-alert
   * @aliases oit:sta
   */
  public function sendTeamsAlert($userMessage) {
    $teams = $this->teamsAlert;
    $teams->sendMessage($userMessage);
    $this->messenger->addMessage('Teams Alert Sent.');
  }

  /**
   * Clean users that haven't accessed the site in over a year.
   *
   * @param int $limit
   *   Maximum number of users to remove (0 = no limit).
   *
   * @command oit:clean-users
   * @aliases oit:cu
   */
  public function cleanUsers($limit = 0) {
    $this->userClean->removeUsers($limit);
  }

  /**
   * Run top service pages report.
   *
   * @command oit:top-service-pages
   * @aliases oit:tsp
   */
  public function topServicePages() {
    $this->topPages->getTopPages();
  }

  /**
   * Run top tutorial pages report.
   *
   * @command oit:top-tutorial-pages
   * @aliases oit:ttp
   */
  public function topTutorialPages() {
    $this->topPages->getTopTutorials();
  }

  /**
   * Delete news nodes older than N years that are not linked anywhere.
   *
   * @command oit:news-delete
   * @aliases oit:nd
   * @argument years Number of years back to use as the deletion cutoff (default: 5).
   * @option dry-run Preview the kill list without deleting anything.
   * @usage drush oit:news-delete
   *   Delete news older than 5 years that are not referenced.
   * @usage drush oit:news-delete 3 --dry-run
   *   Preview what would be deleted with a 3-year cutoff.
   */
  public function newsDelete(int $years = 5, array $options = ['dry-run' => FALSE]): void {
    $dry_run = (bool) $options['dry-run'];

    if ($dry_run) {
      $result = $this->deleteNews->findDeletable($years);
      $this->output()->writeln('DRY RUN — no changes made.');
    }
    else {
      $result = $this->deleteNews->deleteNews($years);
    }

    $deleted_count = count($result['deleted']);
    $skipped_count = count($result['skipped']);
    $verb = $dry_run ? 'Would delete' : 'Deleted';

    $this->output()->writeln("{$verb} {$deleted_count} news node(s) older than {$years} year(s).");

    if ($deleted_count > 0) {
      foreach ($result['deleted'] as $nid => $title) {
        $this->output()->writeln("  - [{$nid}] {$title}");
      }
    }

    if ($skipped_count > 0) {
      $this->output()->writeln("Skipped {$skipped_count} node(s) due to existing links:");
      foreach ($result['skipped'] as $nid => $info) {
        $this->output()->writeln("  - [{$nid}] {$info['title']} — {$info['reason']}");
      }
    }
  }

}
