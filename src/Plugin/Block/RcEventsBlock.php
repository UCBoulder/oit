<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Top pages block.
 *
 * @Block(
 *   id = "rc_events_block",
 *   admin_label = @Translation("Research computing (RC) events block 4")
 * )
 */
class RcEventsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'inline_template',
      '#template' => '<div id="localist-widget-26626794" class="localist-widget"></div><script defer type="text/javascript" src="https://calendar.colorado.edu/widget/view?schools=ucboulder&days=90&num=4&tags=crdds&hideimage=1&target_blank=1&container=localist-widget-26626794&style=none"></script><div id="lclst_widget_footer"></div>',
      '#cache' => [
        'tags' => [
          'rc382:events',
        ],
      ],
    ];
  }

}
