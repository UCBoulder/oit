<?php

namespace Drupal\oit\Plugin\Filter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Replace target_blank with noopener and some styles.
 */
#[Filter(
  id: "filter_noopener",
  title: new TranslatableMarkup("_blank Filter"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Adds rel=noopener to _blank links"),
)]
class FilterNoopener extends FilterBase {

  /**
   * {@inheritdoc}
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
