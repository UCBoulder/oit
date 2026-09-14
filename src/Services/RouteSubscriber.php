<?php

namespace Drupal\oit\Services;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.user.edit_form')) {
      $route->setRequirement('_permission', 'administer users');
    }

    // Webform's custom.js / custom.css asset routes are gated by the
    // 'webform.view' entity access check, which for authenticated-only forms
    // denies anonymous requests. Pantheon's CDN strips cookies from requests
    // for .js/.css paths, so logged-in visitors' browsers hit these routes as
    // anonymous, get a 403, and the resulting "access denied" log entries
    // count toward the autoban threshold. The controller only ever returns the
    // webform's raw custom JS/CSS text (never its configuration), so open
    // these two routes. The webform pages, submissions and configuration
    // routes are untouched.
    $asset_routes = [
      'entity.webform.assets.javascript',
      'entity.webform.assets.css',
    ];
    foreach ($asset_routes as $name) {
      if ($route = $collection->get($name)) {
        $requirements = $route->getRequirements();
        unset($requirements['_entity_access']);
        $requirements['_access'] = 'TRUE';
        $route->setRequirements($requirements);
      }
    }
  }

}
