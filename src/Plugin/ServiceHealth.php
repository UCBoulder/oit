<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
   * Constructs request stuff.
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
    $fieldName = 'field_service_dashboard_category';
    $service_alert_dashboard_field = $this->configFactory->getEditable("field.storage.$entityType.$fieldName");
    $settings = $service_alert_dashboard_field->get('settings');
    // List of categories.
    $dashboard_categories = $settings['allowed_values'];

    foreach ($dashboard_categories as $dashboard_category) {
      $dashboard_category_key = $dashboard_category['value'];
      $dashboard_category = $dashboard_category['label'];
      // Setup array with proper key with category.
      $sa_dashboard_key_category[$dashboard_category_key] = $dashboard_category;
      $entity_storage = $this->entityTypeManager->getStorage('node');
      $query = $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->condition($fieldName, $dashboard_category_key)
        ->condition('status', 1)
        ->sort('created', 'DESC');
      $results = $query->execute();
      if (empty($results)) {
        $category["0-$dashboard_category"] = [
          'service' => $dashboard_category,
          'status' => 0,
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
          $sa_link = $this->nidLink($result, $dashboard_category . ' - ' . $this->t('View Service Alert'), ['text-color--blue']);
          $created = $sa->get('created')->value;
          $timeago = $this->dateFormatter->formatTimeDiffSince($created);
          $timeago .= " " . $this->t('ago');
          $status = $sa->get('field_service_alert_status')->value;
          if ($status == 'Service Issue Reported' || $status == 'Service Issue Updated') {
            $category["2-$dashboard_category"] = [
              'service' => $dashboard_category,
              'status' => 2,
              'link' => $sa_link,
              'button' => $sa_button,
              'last_update' => $timeago,
            ];
          }
          elseif ($status == 'Service Maintenance Scheduled') {
            $category["1-$dashboard_category"] = [
              'service' => $dashboard_category,
              'status' => 1,
              'link' => $sa_link,
              'button' => $sa_button,
              'last_update' => $timeago,
            ];
          }
          else {
            $sa_button = $this->nidLink($result, $this->t('View Latest'), ['button']);
            $sa_link = $this->nidLink($result, $dashboard_category . ' - ' . $this->t('View Service Alert'), ['text-color--blue']);
            $category["0-$dashboard_category"] = [
              'service' => $dashboard_category,
              'status' => 0,
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
  public function serviceLink($category) {
    $service_links = [
      'Buff Portal' => '21566',
      'Canvas' => '19026',
      'Classroom Capture' => '418',
      'Computing Labs' => '413',
      'Federated Identity Service' => '3174',
      'Google Workspace' => '10617',
      'Grouper' => '16743',
      'iClicker' => '243',
      'Identity Manager' => '1169',
      'Kaltura Rich Media Streaming' => '3984',
      'Microsoft Office 365' => '12589',
      'MyCUInfo' => 'https://mycuinfo.colorado.edu',
      'Network' => '248',
      'OIT Data Centers' => '254',
      'Personal Capture' => '25106',
      'PlayPosit' => '21061',
      'Proctorio' => '24631',
      'Qualtrics' => '8615',
      'SensusAccess' => '16521',
      'Sympa Email Lists' => '15775',
      'Turnitin' => '2323',
      'VoiceThread' => '10101',
      'VPN' => '573',
      'Zoom' => '15005',
    ];
    if (isset($service_links[$category]) && is_numeric($service_links[$category])) {
      $service = $this->nidLink($service_links[$category], $category);
    }
    elseif (isset($service_links[$category])) {
      $service = $this->extLink($service_links[$category], $category);
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
   * Make translate work maybe.
   */
  private function t($text) {
    // @codingStandardsIgnoreStart
    return t($text);
    // @codingStandardsIgnoreEnd
  }

}
