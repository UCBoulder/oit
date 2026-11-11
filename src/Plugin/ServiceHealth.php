<?php

namespace Drupal\oit\Plugin;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
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
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * Constructs a new ServiceHealth object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $dateFormatter,
    EntityTypeManagerInterface $entity_type_manager,
    CacheBackendInterface $cache,
  ) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $dateFormatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->cache = $cache;
  }

  /**
   * Service Alert health — thin cache wrapper.
   *
   * @return array<string, array<string, mixed>>
   *   Service health rows keyed by "{status}-{category name}".
   */
  public function serviceHealthLookup(): array {
    $cid = 'oit:service_health_lookup';
    $cached = $this->cache->get($cid);
    if ($cached) {
      return $cached->data;
    }
    $category = $this->buildServiceHealthData();
    $this->cache->set($cid, $category, Cache::PERMANENT, [
      'node_list:service_alert',
      'taxonomy_term_list:service_dashboard_category',
    ]);
    return $category;
  }

  /**
   * Build service alert health data (uncached).
   *
   * @return array<string, array<string, mixed>>
   *   Service health rows keyed by "{status}-{category name}".
   */
  protected function buildServiceHealthData(): array {
    $category = [];
    $entityType = 'node';
    $bundle = 'service_alert';
    $taxonomyName = 'service_dashboard_category';
    $fieldName = 'field_service_alert_dash_cat';

    $entity_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Pass TRUE to get full term entities (avoids N+1 load() calls).
    /** @var \Drupal\taxonomy\TermInterface[] $dashboard_categories */
    $dashboard_categories = $entity_storage->loadTree($taxonomyName, 0, NULL, TRUE);

    // First pass: collect all node IDs per category.
    $category_queries = [];
    $node_storage = $this->entityTypeManager->getStorage($entityType);
    foreach ($dashboard_categories as $term) {
      $dashboard_category_key = $term->id();
      $dashboard_category_name = $term->label();
      $dashboard_category_weight = $term->hasField('field_weight') && !$term->get('field_weight')->isEmpty()
        ? (int) $term->get('field_weight')->value
        : 10;

      $nids = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->condition($fieldName, $dashboard_category_key)
        ->condition('status', 1)
        ->sort('created', 'ASC')
        ->execute();

      $category_queries[$dashboard_category_key] = [
        'name' => $dashboard_category_name,
        'weight' => $dashboard_category_weight,
        'nids' => $nids,
      ];
    }

    // Bulk-load all nodes at once.
    $all_nids = array_merge(...array_map(
      fn($q) => array_values($q['nids']),
      array_values($category_queries)
    ));
    $nodes = !empty($all_nids) ? $node_storage->loadMultiple($all_nids) : [];

    // Second pass: build category data using the pre-loaded node map.
    foreach ($category_queries as $dashboard_category_key => $query_data) {
      $dashboard_category_name = $query_data['name'];
      $dashboard_category_weight = $query_data['weight'];
      $results = $query_data['nids'];

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
        // Last node wins per category — intentional product behavior.
        foreach ($results as $result) {
          $sa = $nodes[$result];
          $sa_button = $this->nidLink((int) $result, $this->t('View'), ['button']);
          $sa_link = $this->nidLink((int) $result, $dashboard_category_name . ' - ' . $this->t('View Service Alert'), ['text-color--blue']);
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
            $sa_button = $this->nidLink((int) $result, $this->t('View Latest'), ['button']);
            $sa_link = $this->nidLink((int) $result, $dashboard_category_name . ' - ' . $this->t('View Service Alert'), ['text-color--blue']);
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
   *
   * @return array<int, string|\Stringable>
   *   Rendered status labels keyed by status code.
   */
  public function serviceHealthStatusByKey(): array {
    $status_key = [
      0 => $this->t('No service issue')->render(),
      1 => $this->t('Maintenance scheduled/ongoing')->render(),
      2 => $this->t('Service issue')->render(),
    ];
    return $status_key;
  }

  /**
   * Reduce duplicates.
   *
   * @param array<string, array<string, mixed>> $category
   *   Service health rows, as built by serviceHealthLookup().
   *
   * @return array<string, array{status: mixed, key: string}>
   *   The highest-status row key per service name.
   */
  public function removeDuplicates($category): array {
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
   *
   * @param string $category
   *   The category name used as link text.
   * @param int|string $tid
   *   The taxonomy term ID of the category.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The link markup, or the plain category name when there is no link.
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
   *
   * Builds the anchor tag manually to avoid Link::toString() bubbling cache
   * metadata (domain context from domain_source) into the render pipeline.
   *
   * @param int $nid
   *   The node ID.
   * @param string $text
   *   The link text.
   * @param list<string> $class
   *   CSS classes for the anchor.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The anchor markup.
   */
  private function nidLink(int $nid, string $text, array $class = []): MarkupInterface|string {
    $id = strtolower(str_replace(' ', '', strip_tags((string) $text)));
    $path = Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString();
    $class_attr = $class ? ' class="' . implode(' ', $class) . '"' : '';
    $title_attr = ' title="' . htmlspecialchars((string) $text, ENT_QUOTES) . '"';
    return Markup::create('<a href="' . $path . '" id="' . $id . '"' . $class_attr . $title_attr . '>' . $text . '</a>');
  }

  /**
   * External link.
   *
   * @param string $link
   *   The external URI.
   * @param string $text
   *   The link text.
   * @param array<int, string> $class
   *   CSS classes for the anchor.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered link.
   */
  private function extLink($link, $text, $class = []): MarkupInterface {
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
   *
   * @param int|string $status
   *   The service health status code.
   *
   * @return string
   *   The SVG markup.
   */
  public function statusCircle($status): string {
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
