<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\oit\Plugin\GoogleSheetsTopLinks;

/**
 * Top Tutorials block.
 *
 * @Block(
 *   id = "front_top_tutorial_block",
 *   admin_label = @Translation("Front Top Tutorials")
 * )
 */
class FrontTopTutorialsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $top = new GoogleSheetsTopLinks('1A_d2mwTnWZLEJjURD4nHSCGif6vgrQn65tmQPcfd7TI', 1207505613, 'a,b', 1, 9);
    return [
      '#type' => 'inline_template',
      '#template' => '<h2><a href="{{ servicesLink }}">{% trans %}Top Tutorials{% endtrans %}</a></h2>{{ topPageList | raw }}',
      '#context' => [
        'topPageList' => $top->getTopLinksData(),
        'servicesLink' => '/services#tut',
      ],
    ];
  }

}
