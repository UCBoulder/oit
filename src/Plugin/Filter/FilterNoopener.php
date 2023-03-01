<?php

namespace Drupal\oit\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Replace target_blank with noopener and some styles.
 *
 * @Filter(
 *   id = "filter_noopener",
 *   title = @Translation("_blank Filter"),
 *   description = @Translation("Adds rel=noopener to _blank links"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterNoopener extends FilterBase {

  /**
   * Find and replace target=_blank links.
   */
  public function process($text, $langcode) {
    $patterns = [];
    $patterns[0] = '/(target.._blank.)(.*)(<.a>)/';
    $replacements = [];
    $replacements[0] = '$1 rel="noopener"$2 <span class="oit-newtab-fontz" style="color: #575a5c;"></span>$3';
    $cleaned = preg_replace($patterns, $replacements, $text);
    return new FilterProcessResult($cleaned);
  }

}
