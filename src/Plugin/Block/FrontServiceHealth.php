<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oit\Plugin\ServiceHealth;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service Health block.
 *
 * @Block(
 *   id = "service_health_block",
 *   admin_label = @Translation("Service Health front page block")
 * )
 */
class FrontServiceHealth extends BlockBase implements
  ContainerFactoryPluginInterface {

  /**
   * Logger Factory.
   *
   * @var \Drupal\oit\Plugin\ServiceHealth
   */
  protected $serviceHealth;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container pulled in.
   * @param array $configuration
   *   Configuration added.
   * @param string $plugin_id
   *   Plugin_id added.
   * @param mixed $plugin_definition
   *   Plugin_definition added.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oit.servicehealth'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param mixed $plugin_definition
   *   Plugin Definition mixed.
   * @param \Drupal\Core\Entity\ServiceHealth $service_health
   *   Invokes renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ServiceHealth $service_health) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serviceHealth = $service_health;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $service_dashboard = $this->serviceHealth;
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
    // Sort categories with status 0 by weight field.
    $status_zero_categories = [];
    foreach ($clean_category as $service_name => $cat) {
      if ($cat['status'] === 0) {
        $service_key = $cat['key'];
        // Get weight from original category array.
        $weight = $category[$service_key]['weight'] ?? 10;
        $status_zero_categories[$service_name] = [
          'status' => $cat['status'],
          'key' => $service_key,
          'weight' => $weight,
        ];
      }
    }
    // Sort by weight (lower weight values appear first).
    uasort($status_zero_categories, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });
    foreach ($status_zero_categories as $service_name => $cat) {
      if ($n < 9) {
        $n++;
        $service_key = $cat['key'];
        $svg = $service_dashboard->statusCircle($category[$service_key]['status']);
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
    $services .= "</ul>";
    return [
      '#type' => 'inline_template',
      '#template' => '<div><h2><a href="{{ servicesLink }}">{% trans %} Service Health {% endtrans %}</a></h2>{{ content | raw }}</div>',
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
