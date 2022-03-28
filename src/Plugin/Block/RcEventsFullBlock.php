<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Top pages block.
 *
 * @Block(
 *   id = "rc_events_full_block",
 *   admin_label = @Translation("Full Page - Research computing (RC) events block 15")
 * )
 */
class RcEventsFullBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'inline_template',
      '#template' => '<div id="localist-widget-25618727" class="localist-widget"></div><script defer type="text/javascript" src="https://calendar.colorado.edu/widget/view?schools=ucboulder&days=90&num=15&tags=crdds&hideimage=1&target_blank=1&container=localist-widget-25618727&style=none"></script><div id="lclst_widget_footer"></div>',
      '#cache' => [
        'tags' => [
          'rc382:events',
        ],
      ],
    ];
  }

}
