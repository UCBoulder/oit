<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Top pages block.
 *
 * @Block(
 *   id = "oit_header",
 *   admin_label = @Translation("OIT header link")
 * )
 */
class OitHeader extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = sprintf(
      "<a href='%s'><span class='oit-scrolled'>%s</span> <span class='oit-full'>%s</span></a>",
      '/',
      'OIT',
      $this->t('Office of Information Technology')
    );
    return [
      '#markup' => $block,
    ];
  }

}
