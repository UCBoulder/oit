<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Current service health.
 *
 * @ServiceHealth (
 *   id = "servicehealth",
 *   title = @Translation("Service Health"),
 *   description = @Translation("Service Health pulled from service alerts")
 * )
 */
class ServiceHealth {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Date formatter service object.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ServiceHealth object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $dateFormatter,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $dateFormatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Service Alert health.
   */
  public function serviceHealthLookup() {
    $category = [];
    $entityType = 'node';
    $bundle = 'service_alert';
    $taxonomyName = 'service_dashboard_category';
    $fieldName = 'field_service_alert_dash_cat';

    $entity_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $dashboard_categories = $entity_storage->loadTree($taxonomyName);

    foreach ($dashboard_categories as $dashboard_category) {
      $dashboard_category_key = $dashboard_category->tid;
      $dashboard_category_name = $dashboard_category->name;
      // Setup array with proper key with category.
      $sa_dashboard_key_category[$dashboard_category_key] = $dashboard_category_name;
      // Load full term to get weight field.
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $term = $term_storage->load($dashboard_category_key);
      $dashboard_category_weight = $term->hasField('field_weight') && !$term->get('field_weight')->isEmpty()
        ? (int) $term->get('field_weight')->value
        : 10;
      $entity_storage = $this->entityTypeManager->getStorage('node');
      $query = $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->condition($fieldName, $dashboard_category_key)
        ->condition('status', 1)
        ->sort('created', 'ASC');
      $results = $query->execute();
      if (empty($results)) {
        $category["0-$dashboard_category_name"] = [
          'service' => $dashboard_category_name,
          'tid' => $dashboard_category_key,
          'status' => 0,
          'weight' => $dashboard_category_weight,
          'link' => '',
          'button' => '',
          'last_update' => '',
        ];
      }
      else {
        foreach ($results as $result) {
          $node_storage = $this->entityTypeManager->getStorage($entityType);
          $sa = $node_storage->load($result);
          $sa_button = $this->nidLink($result, $this->t('View'), ['button']);
          $sa_link = $this->nidLink($result, $dashboard_category_name . ' - ' . $this->t('View Service Alert'), ['text-color--blue']);
          $created = $sa->get('created')->value;
          $timeago = $this->dateFormatter->formatTimeDiffSince($created);
          $timeago .= " " . $this->t('ago');
          $status = $sa->get('field_service_alert_status')->value;
          if ($status == 'Service Issue Reported' || $status == 'Service Issue Updated') {
            $category["2-$dashboard_category_name"] = [
              'service' => $dashboard_category_name,
              'tid' => $dashboard_category_key,
              'status' => 2,
              'weight' => $dashboard_category_weight,
              'link' => $sa_link,
              'button' => $sa_button,
              'last_update' => $timeago,
            ];
          }
          elseif ($status == 'Service Maintenance Scheduled') {
            $category["1-$dashboard_category_name"] = [
              'service' => $dashboard_category_name,
              'tid' => $dashboard_category_key,
              'status' => 1,
              'weight' => $dashboard_category_weight,
              'link' => $sa_link,
              'button' => $sa_button,
              'last_update' => $timeago,
            ];
          }
          else {
            $sa_button = $this->nidLink($result, $this->t('View Latest'), ['button']);
            $sa_link = $this->nidLink($result, $dashboard_category_name . ' - ' . $this->t('View Service Alert'), ['text-color--blue']);
            $category["0-$dashboard_category_name"] = [
              'service' => $dashboard_category_name,
              'tid' => $dashboard_category_key,
              'status' => 0,
              'weight' => $dashboard_category_weight,
              'link' => '',
              'button' => $sa_button,
              'last_update' => $timeago,
            ];
          }
        }
      }

    }

    return $category;
  }

  /**
   * Service Alert health.
   */
  public function serviceHealthStatusByKey() {
    $status_key = [
      0 => $this->t('No service issue')->render(),
      1 => $this->t('Maintenance scheduled/ongoing')->render(),
      2 => $this->t('Service issue')->render(),
    ];
    return $status_key;
  }

  /**
   * Reduce duplicates.
   */
  public function removeDuplicates($category) {
    $service_track = [];
    foreach ($category as $sh_key => $cat) {
      $status = $cat['status'];
      $service = $cat['service'];
      if (isset($service_track[$service])) {
        if ($service_track[$service]['status'] < $status) {
          $service_track[$service] = [
            'status' => $status,
            'key' => $sh_key,
          ];
        }
      }
      else {
        $service_track[$service] = [
          'status' => $status,
          'key' => $sh_key,
        ];
      }
    }

    return $service_track;
  }

  /**
   * Link service category to the correct service.
   */
  public function serviceLink($category, $tid) {
    $entity_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term = $entity_storage->load($tid);
    $ref_nid = $term->get('field_sa_dash_cat_node_id')->value;

    if (isset($ref_nid) && is_numeric($ref_nid)) {
      $service = $this->nidLink($ref_nid, $category);
    }
    elseif (isset($ref_nid)) {
      $service = $this->extLink($ref_nid, $category);
    }
    else {
      $service = $category;
    }

    return $service;
  }

  /**
   * Link to a node.
   */
  private function nidLink($nid, $text, $class = []) {
    $id = strtolower(str_replace(' ', '', $text));
    // Create Link to node.
    $link_options = [
      'attributes' => [
        'class' => $class,
        'id' => $id,
        'title' => $text,
      ],
    ];
    $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
    $url->setOptions($link_options);
    $link = Link::fromTextAndUrl($text, $url);
    return $link->toString();
  }

  /**
   * External link.
   */
  private function extLink($link, $text, $class = []) {
    $id = strtolower(str_replace(' ', '', $text));
    $link_options = [
      'attributes' => [
        'class' => $class,
        'id' => $id,
        'title' => $text,
      ],
    ];
    $url = Url::fromUri($link);
    $url->setOptions($link_options);
    $link = Link::fromTextAndUrl($text, $url);
    return $link->toString();
  }

  /**
   * Returns svg circle for given status.
   */
  public function statusCircle($status) {
    return "<svg height='20' width='20' class='service_health sa-$status'><circle cx='10' cy='10' r='8' stroke='black' stroke-width='.3' /></svg>";
  }

  /**
   * Wraps the global t() function for use inside this class.
   *
   * @param string $text
   *   The string to translate.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated string.
   */
  private function t($text) {
    // @codingStandardsIgnoreStart
    return t($text);
    // @codingStandardsIgnoreEnd
  }

}
