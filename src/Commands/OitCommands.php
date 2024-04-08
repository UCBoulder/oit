<?php

namespace Drupal\oit\Commands;

use Drush\Commands\DrushCommands;

/**
 * Various utility commands for OIT.
 */
class OitCommands extends DrushCommands {

  /**
   * FULL Rebuild Princess List.
   *
   * @command oit:reload-princess
   * @aliases oit-rp
   */
  public function reloadPrincess() {
    $princess = \Drupal::service('servicenow.princess.list');
    $princess->reload();
    $princess->cron(0);
  }

}
