<?php

namespace Drupal\oit\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\oit\Plugin\OitImageStyled;
use Drupal\oit\Services\HeroImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for the oittoken token type.
 */
class TokenHooks {

  use StringTranslationTrait;

  /**
   * The token type machine name.
   */
  protected const TOKEN_TYPE = 'oittoken';

  /**
   * The default service alert image, relative to the site root.
   */
  protected const DEFAULT_ALERT_IMAGE = '/sites/default/files/sa_images/sa_other.png';

  /**
   * Constructs a new TokenHooks object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\oit\Services\HeroImage $heroImage
   *   The OIT hero image service.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    #[Autowire(service: 'oit.hero_image')]
    protected HeroImage $heroImage,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $type = [
      'name' => $this->t('OIT'),
      'description' => $this->t('Custom OIT tokens.'),
    ];
    $node['tweet_pic'] = [
      'name' => $this->t("Twitter Picture"),
      'description' => $this->t('Sets the twitter picture for news/service alerts.'),
    ];
    $node['og_image'] = [
      'name' => $this->t("OG Image"),
      'description' => $this->t('Sets the Open Graph image for news/service alerts at 1200x630 for LinkedIn and other platforms.'),
    ];
    $node['who_i_is'] = [
      'name' => $this->t("Who I Is"),
      'description' => $this->t('Displays users name.'),
    ];
    return [
      'types' => ['oittoken' => $type],
      'tokens' => ['oittoken' => $node],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    if ($type !== self::TOKEN_TYPE) {
      return $replacements;
    }

    $node = $data['node'] ?? NULL;
    $node = $node instanceof NodeInterface ? $node : NULL;

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'who_i_is':
          $replacements[$original] = $this->currentUserName();
          break;

        case 'tweet_pic':
          if ($node !== NULL) {
            $replacements[$original] = $this->imageToken($node, 'large');
          }
          break;

        case 'og_image':
          if ($node !== NULL) {
            $replacements[$original] = $this->imageToken($node, 'social_share');
          }
          break;
      }
    }

    return $replacements;
  }

  /**
   * Gets the current user's display name for the who_i_is token.
   *
   * @return string
   *   The HTML-escaped user name, or an empty string.
   */
  protected function currentUserName(): string {
    if ($this->currentUser->isAnonymous()) {
      return '';
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if (!$user || !$user->hasField('field_user_name') || $user->get('field_user_name')->isEmpty()) {
      return '';
    }

    return Html::escape($user->get('field_user_name')->value);
  }

  /**
   * Builds the styled image URL for the tweet_pic and og_image tokens.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node the token is being replaced on.
   * @param string $style
   *   The image style machine name.
   *
   * @return string
   *   The styled image URL, or an empty string.
   */
  protected function imageToken(NodeInterface $node, string $style): string {
    if ($node->getType() === 'news') {
      $fid = $this->heroImage->getFileId($node);
      return $fid ? $this->styledImageUrl($fid, $style) : '';
    }

    if ($node->getType() === 'service_alert') {
      if (!$node->hasField('field_service_alert_dash_cat') || $node->get('field_service_alert_dash_cat')->isEmpty()) {
        return '';
      }
      $fid = $this->dashboardImageFileId($node);
      return $fid ? $this->styledImageUrl($fid, $style) : $this->defaultAlertImage();
    }

    return '';
  }

  /**
   * Gets the dashboard category term's twitter image file ID.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service alert node.
   *
   * @return int|null
   *   The file entity ID, or NULL when there is no dashboard category, the
   *   term doesn't load, or the term has no twitter image.
   */
  protected function dashboardImageFileId(NodeInterface $node): ?int {
    if (!$node->hasField('field_service_alert_dash_cat') || $node->get('field_service_alert_dash_cat')->isEmpty()) {
      return NULL;
    }

    $term_id = $node->get('field_service_alert_dash_cat')->getValue()[0]['target_id'] ?? NULL;
    if (!$term_id) {
      return NULL;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
    if (!$term || $term->get('field_twitter_image')->isEmpty()) {
      return NULL;
    }

    return (int) $term->get('field_twitter_image')->getValue()[0]['target_id'];
  }

  /**
   * Wraps OitImageStyled to build a styled image URL from a file ID.
   *
   * A separate method so unit tests can stub it, since OitImageStyled calls
   * the static ImageStyle::load().
   *
   * @param int $fid
   *   The file entity ID.
   * @param string $style
   *   The image style machine name.
   *
   * @return string
   *   The styled image URL.
   */
  protected function styledImageUrl(int $fid, string $style): string {
    return (new OitImageStyled($fid, $style, '', $this->entityTypeManager))->getImageUrl();
  }

  /**
   * Builds the default service alert image URL.
   *
   * @return string
   *   The absolute URL of the default service alert image.
   */
  protected function defaultAlertImage(): string {
    return $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . self::DEFAULT_ALERT_IMAGE;
  }

}
