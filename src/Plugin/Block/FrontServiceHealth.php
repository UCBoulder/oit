<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\oit\Plugin\ServiceHealth;
use Drupal\Core\Block\BlockBase;

/**
 * Service Health block.
 *
 * @Block(
 *   id = "service_health_block",
 *   admin_label = @Translation("Service Health front page block")
 * )
 */
class FrontServiceHealth extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $service_dashboard = new ServiceHealth();
    $category = $service_dashboard->serviceHealthLookup();
    krsort($category);
    $clean_category = $service_dashboard->removeDuplicates($category);
    $services = "<ul class='service-health gray-links no-list-style'>";
    $n = 0;
    foreach ($clean_category as $key => $cat) {
      if ($n < 9) {
        if ($cat['status'] > 0) {
          $n++;
          $svg = $service_dashboard->statusCircle($cat['status']);
          $service_name = $key;
          $service_key = $cat['key'];
          $service_name_id = strtolower(str_replace(' ', '', $service_name));
          $link = !empty($category[$service_key]['link']) ? $category[$service_key]['link'] : '';
          $services .= "<li class='truncate'>$svg ";
          if (empty($link)) {
            $services .= "<a href='/service-health#$service_name_id'>$service_name</a>";
          }
          else {
            $services .= "$link";
          }
          $services .= "</li>";
        }
      }
    }
    // Force the following order for for all status 0 categories.
    $service_order = [
      'Network',
      'Canvas',
      'Zoom',
      'Buff Portal',
      'MyCUInfo',
      'Identity Manager',
      'Microsoft Office 365',
      'Google Workspace',
      'OIT Data Centers',
    ];
    foreach ($service_order as $so) {
      if ($n < 9) {
        // Don't show the category if it already is shown with a higher status.
        if (isset($category["1-$so"]) || isset($category["2-$so"])) {
          continue;
        }
        if (isset($category["0-$so"])) {
          $n++;
          $svg = $service_dashboard->statusCircle($category["0-$so"]['status']);
          $service_name = $category["0-$so"]['service'];
          $service_name_id = strtolower(str_replace(' ', '', $service_name));
          $link = !empty($category["0-$so"]['link']) ? $category["0-$so"]['link'] : '';
          $services .= "<li class='truncate'>$svg ";
          if (empty($link)) {
            $services .= "<a href='/service-health#$service_name_id'>$service_name</a>";
          }
          else {
            $services .= "$link";
          }
          $services .= "</li>";
        }
      }
    }
    $services .= "</ul>";
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="heading-underline"><h2><a href="{{ servicesLink }}">{% trans %} Service Health {% endtrans %}</a></h2>{{ content | raw }}</div>',
      '#context' => [
        'content' => $services,
        'servicesLink' => '/service-health',
      ],
      '#cache' => [
        'tags' => [
          'node_type:service_alert',
        ],
      ],
    ];
  }

}
