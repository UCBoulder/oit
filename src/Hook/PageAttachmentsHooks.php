<?php

namespace Drupal\oit\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\oit\Plugin\Domain;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for page attachments.
 */
class PageAttachmentsHooks {

  /**
   * The downloads page node ID.
   */
  protected const DOWNLOADS_NODE_ID = 262;

  /**
   * The Google site verification token for the OIT domain.
   */
  protected const GOOGLE_SITE_VERIFICATION = '7W5qMq8L0e0Ar6WDHCTrcC5IDXMBDj1Cm1DL0evR32o';

  /**
   * The Goodkind chatbot bot ID.
   */
  protected const CHATBOT_AI_BOT_ID = '6765a3c67b1f300012149d6e';

  /**
   * The Goodkind chatbot API key.
   */
  protected const CHATBOT_API_KEY = '659425d1a56d1027865e1d658b27679e288bdd07adb4814d0d812a69bba6b429c77939ec0214f41e731ec17a26ce1ddcff47d138efbfc5199ff87a2569ed8115f4fbfca462109099a2cdf031b6c7b55fb3734024368179943a227b5cc6ec566bb01a45695e42a074f00d15ad0029775f46b7ad';

  /**
   * The Goodkind chatbot widget script URL.
   */
  protected const CHATBOT_WIDGET_SCRIPT_URL = 'https://widget.goodkind.com/gk-chatbot-widget.js';

  /**
   * Constructs a new PageAttachmentsHooks object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\oit\Plugin\Domain $domain
   *   The OIT domain service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    #[Autowire(service: 'oit.domain')]
    protected Domain $domain,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_page_attachments_alter().
   *
   * @param array $attachments
   *   The page attachments, passed by reference.
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $node = $this->routeMatch->getParameter('node');
    if (!is_null($node)) {
      $id = is_string($node) ? $node : $node->id();
      if ($id == self::DOWNLOADS_NODE_ID) {
        $attachments['#attached']['library'][] = 'oit/listjs';
        $attachments['#attached']['library'][] = 'oit/downloads_search';
      }
      if (!is_string($node)) {
        if ($node->getType() == 'tutorial') {
          $attachments['#attached']['library'][] = 'dingo/tutorial';
        }
      }
    }
    $domain = $this->domain->getDomain();
    if ($domain == 'oit') {
      $google_site_verify = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'google-site-verification',
          'content' => self::GOOGLE_SITE_VERIFICATION,
        ],
      ];
      $attachments['#attached']['html_head'][] = [$google_site_verify, 'google-site-verification'];

      $roles = $this->currentUser->getRoles();
      $environment = getenv('PANTHEON_ENVIRONMENT');
      if (!in_array('administrator', $roles) && $environment != 'local') {
        // Goodkind chatbot widget.
        $chatbot_config = [
          'aiBotId' => self::CHATBOT_AI_BOT_ID,
          'apiKey' => self::CHATBOT_API_KEY,
        ];
        $chatbot_settings = [
          '#tag' => 'script',
          '#attributes' => ['type' => 'text/javascript'],
          '#value' => 'window.gkCBWConfig = ' . Json::encode($chatbot_config) . ';',
        ];
        $attachments['#attached']['html_head'][] = [$chatbot_settings, 'gk-chatbot-widget-config'];
        $chatbot_widget = [
          '#tag' => 'script',
          '#attributes' => [
            'type' => 'module',
            'src' => self::CHATBOT_WIDGET_SCRIPT_URL,
            'defer' => TRUE,
          ],
          '#value' => '',
        ];
        $attachments['#attached']['html_head'][] = [$chatbot_widget, 'gk-chatbot-widget'];
      }
    }
  }

}
