<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oit\Plugin\OitImageStyled;
use Drupal\oit\Plugin\TeamsAlert;
use Drupal\oit\Services\HeroImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for entity operations.
 */
class EntityHooks {

  /**
   * Constructs a new EntityHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\oit\Plugin\TeamsAlert $teamsAlert
   *   The OIT Teams alert service.
   * @param \Drupal\oit\Services\HeroImage $heroImage
   *   The OIT hero image service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'oit.teamsalert')]
    protected TeamsAlert $teamsAlert,
    #[Autowire(service: 'oit.hero_image')]
    protected HeroImage $heroImage,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (method_exists($entity, 'getType')) {
      if ($entity->getType() == 'news') {
        $this->updateNews($entity);
      }
      if ($entity->getType() == 'service_alert') {
        $this->updateServiceAlert($entity);
      }
      if ($entity->getEntityType()->id() == 'node') {
        // Allows for clearing of blocks based on node type.
        $tags = ['node_type:' . $entity->getType()];
        Cache::invalidateTags($tags);
      }
    }
    // Set image alt.
    if (method_exists($entity, 'bundle')) {
      if ($entity->bundle() == 'pictures') {
        $entity->field_finished_picture->alt = $entity->get('title')->value;
      }
    }
  }

  /**
   * Implements hook_entity_create().
   */
  #[Hook('entity_create')]
  public function entityCreate(EntityInterface $entity): void {
    if ($entity->getEntityType()->getBundleEntityType() == 'node_type' && $entity->getType() == 'webform') {
      // Set access control set to Authenticated.
      $entity->set('field_access_control_2', '475');
    }
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityType()->getBundleEntityType() == 'node_type' && $entity->getType() == 'webform') {
      if ($webform_id = $entity->get('webform')->getValue()) {
        $webform_id = $webform_id[0]['target_id'];
        $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
        // Report error if webform node is pointing to a deleted webform.
        if ($webform == NULL && $webform_id != 0) {
          $this->teamsAlert->sendMessage('Webform no longer exists but is set on node: ' . $entity->id(), ['live']);
          $this->loggerFactory->get('oit')->error('Webform no longer exists but is set on node: ' . $entity->id());
        }
      }
    }
  }

  /**
   * Sets news social metadata images and the unpublish date.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The news entity being saved.
   */
  protected function updateNews(EntityInterface $entity): void {
    $meta_info = json_decode($entity->get('field_meta_tags')->getValue()[0]['value'], TRUE) ?? [];
    $is_temp_image = (isset($meta_info['og_image']) && strpos($meta_info['og_image'], '/system/temporary') !== FALSE)
      || (isset($meta_info['twitter_cards_image']) && strpos($meta_info['twitter_cards_image'], '/system/temporary') !== FALSE);
    $og_using_large = isset($meta_info['og_image']) && strpos($meta_info['og_image'], '/styles/large/') !== FALSE;
    if (!isset($meta_info['twitter_cards_image']) || !isset($meta_info['og_image']) || $is_temp_image || $og_using_large) {
      $image_id = $this->heroImage->getFileId($entity);
      if (!empty($image_id)) {
        $twitter_style = new OitImageStyled($image_id, 'large', '', $this->entityTypeManager);
        $twitter_styled = $twitter_style->getImageUrl();
        if ($twitter_styled && (!isset($meta_info['twitter_cards_image']) || $meta_info['twitter_cards_image'] != NULL)) {
          $meta_info['twitter_cards_image'] = $twitter_styled;
        }
        $og_style = new OitImageStyled($image_id, 'social_share', '', $this->entityTypeManager);
        $og_styled = $og_style->getImageUrl();
        if ($og_styled && (!isset($meta_info['og_image']) || $meta_info['og_image'] != NULL)) {
          $meta_info['og_image'] = $og_styled;
        }
        $meta_serial = json_encode($meta_info);
        $entity->set('field_meta_tags', $meta_serial);
      }
    }
    if ($entity->getType() == 'news' && $entity->get('field_news_archive')->getValue()[0]['value'] == 2) {
      $entity->set('unpublish_on', strtotime("100 days"));
    }
  }

  /**
   * Sets service alert social metadata images.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The service alert entity being saved.
   */
  protected function updateServiceAlert(EntityInterface $entity): void {
    $meta_info = json_decode($entity->get('field_meta_tags')->getValue()[0]['value']) ?? [];
    if ((!isset($meta_info->twitter_cards_image) || !isset($meta_info->og_image)) && $entity->get('field_service_alert_dash_cat')) {
      if (is_object($meta_info)) {
        $meta_info->twitter_cards_type = "summary";
      }
      else {
        $meta_info['twitter_cards_type'] = "summary";
      }

      $set_dashboard = $entity->get('field_service_alert_dash_cat')->getValue()[0]['target_id'];

      $taxonomy = $this->entityTypeManager->getStorage('taxonomy_term')->load($set_dashboard);
      $tax_image = $taxonomy->get('field_twitter_image');
      $image_id = $tax_image->getValue()[0]['target_id'] ?? NULL;
      $image_url = '';
      if ($image_id) {
        $image_style = new OitImageStyled($image_id, 'large', '', $this->entityTypeManager);
        $image_url = $image_style->getImageUrl();
      }

      $host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

      if ($image_url) {
        $social_image = $image_url;
      }
      else {
        $social_image = "$host/sites/default/files/sa_images/sa_other.png";
      }
      $meta = [];
      if (!isset($meta_info->twitter_cards_image) || $meta_info->twitter_cards_image != NULL) {
        $meta['twitter_cards_image'] = $social_image;
      }
      else {
        $meta['twitter_cards_image'] = $meta_info->twitter_cards_image;
      }
      if (!isset($meta_info->og_image) || $meta_info->og_image != NULL) {
        $meta['og_image'] = $social_image;
      }
      else {
        $meta['og_image'] = $meta_info->og_image;
      }
      $meta_serial = serialize($meta);
      $entity->set('field_meta_tags', $meta_serial);
    }
  }

}
