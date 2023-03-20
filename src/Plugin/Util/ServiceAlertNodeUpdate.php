<?php

namespace Drupal\oit\Plugin\Util;


/**
 * Update servicenow nodes to combine all fields into the body.
 */
class ServiceAlertNodeUpdate {
  /**
   * Servicenow URL.
   *
   * @var string
   */
  private $key;

  /**
   * Field HTML.
   *
   * @var string
   */
  private $descriptionHtml;

  /**
   * Function to decrypt key and set.
   */
  public function __construct() {
    $query = \Drupal::entityQuery('node');
    $group = $query
      ->orConditionGroup()
      ->condition('field_service_alert_impact', '', '<>')
      ->condition('field_service_alert_scope', '', '<>')
      ->condition('field_service_alert_aff_serv', '', '<>')
      ->condition('field_service_alert_aff_bldg', '', '<>')
      ->condition('field_service_alert_main_impact', '', '<>')
      ->condition('field_service_alert_add_vendor', '', '<>')
      ->condition('field_service_alert_add_uis', '', '<>');
    $results = $query->condition($group)
                     ->condition('type', 'service_alert')
                     ->sort('nid', 'DESC')
                     ->range(0, 10)
                     ->execute();
    foreach ($results as $result) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($result);
      $this->descriptionHtml = $node->get('body')->value;
      $fields = [
        'field_service_alert_impact' => 'Impact',
        'field_service_alert_scope' => 'Scope',
        'field_service_alert_aff_serv' => 'Affected Services',
        'field_service_alert_aff_bldg' => 'Affected Buildings',
        'field_service_alert_main_impact' => 'For More Information',
        'field_service_alert_add_vendor' => 'Additional Information from Vendor',
        'field_service_alert_add_uis' => 'Additional Information from UIS',
      ];
      foreach ($fields as $key => $field) {
        $this->descriptionHtml .= $this->getBodyField($node, $key, $field);
      }
      kint($result);
      kint($this->descriptionHtml);
      $node->set('body', $this->descriptionHtml);
      $node->body->format = 'rich_text';
      $node->set('field_sympa_send', 0);
      $node->save();
    }
  }

  /**
   * Return field with proper header and html.
   */
  private function getBodyField($node, $field, $header) {
    $value = $node->get($field)->value;
    $html = '';
    if ($value !== NULL) {
      $html = "<h2>$header</h2>$value";
      $node->set($field, '');
    }
    return $html;
  }

  /**
   * Return key.
   */
  public function getKey() {
    return $this->key;
  }

}
