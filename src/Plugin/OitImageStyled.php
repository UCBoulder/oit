<?php

namespace Drupal\oit\Plugin;

use Drupal\image\Entity\ImageStyle;
use Drupal\file\Entity\File;

/**
 * Environment icon to be used on header title.
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
   * Take image id and style wanted and return url.
   */
  public function __construct($image_id, $style = "max_325x325", $filefield_replace = '') {
    $style = ImageStyle::load($style);
    if (!empty($image_id)) {
      $photo_file = File::load($image_id);
      $photo_uri = $photo_file->getFileUri();
      if (preg_match('/^public:\/\/filefield_paths/i', $photo_uri) && !empty($filefield_replace)) {
        $photo_uri = preg_replace('/filefield_paths/i', $filefield_replace, $photo_uri);
      }
      $image_url = $style->buildUrl($photo_uri);
    }
    $this->imageUrl = $image_url;
  }

  /**
   * Return icon.
   */
  public function getImageUrl() {
    return $this->imageUrl;
  }

}
