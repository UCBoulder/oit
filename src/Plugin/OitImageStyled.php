<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Plugin to generate a styled image URL from a file entity ID.
 *
 * @OitImageStyled(
 *   id = "imagestyle",
 *   title = @Translation("Image Style"),
 *   description = @Translation("Get url of image using an image style.")
 * )
 */
class OitImageStyled {
  /**
   * Return Styled Image url.
   *
   * @var string
   */
  private $imageUrl;

  /**
   * Constructs a new OitImageStyled object and generates the styled image URL.
   *
   * @param int $image_id
   *   The file entity ID of the image.
   * @param string $style
   *   The image style machine name.
   * @param string $filefield_replace
   *   Optional path replacement for filefield_paths URIs.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   The entity type manager.
   */
  public function __construct($image_id, $style = "max_325x325", $filefield_replace = '', ?EntityTypeManagerInterface $entityTypeManager = NULL) {
    $style = ImageStyle::load($style);
    $image_url = '';
    if (!empty($image_id)) {
      $photo_file = $entityTypeManager->getStorage('file')->loadUnchanged($image_id);
      $photo_uri = $photo_file->getFileUri();
      if (preg_match('/^public:\/\/filefield_paths/i', $photo_uri) && !empty($filefield_replace)) {
        $photo_uri = preg_replace('/filefield_paths/i', $filefield_replace, $photo_uri);
      }
      elseif (preg_match('/^temporary:\/\/filefield_paths/i', $photo_uri) && !empty($filefield_replace)) {
        $photo_uri = preg_replace('/^temporary:\/\/filefield_paths/i', 'public://' . $filefield_replace, $photo_uri);
      }
      $image_url = $style->buildUrl($photo_uri);
    }
    $this->imageUrl = $image_url;
  }

  /**
   * Return the styled image URL.
   *
   * @return string
   *   The absolute URL of the styled image.
   */
  public function getImageUrl() {
    return $this->imageUrl;
  }

}
