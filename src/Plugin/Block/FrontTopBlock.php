<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\oit\Plugin\GoogleSheetsTopLinks;

/**
 * Top pages block.
 *
 * @Block(
 *   id = "front_top_block",
 *   admin_label = @Translation("Front Top Pages")
 * )
 */
class FrontTopBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $top = new GoogleSheetsTopLinks('1A_d2mwTnWZLEJjURD4nHSCGif6vgrQn65tmQPcfd7TI', 0, 'a,b', 1, 9);
    return [
      '#type' => 'inline_template',
      '#template' => '<h2><a href="{{ servicesLink }}">{% trans %} Top Service Pages {% endtrans %}</a></h2>{{ topPageList | raw }}',
      '#context' => [
        'topPageList' => $top->getTopLinksData(),
        'servicesLink' => '/services#az',
      ],
    ];
  }

}
