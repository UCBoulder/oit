<?php

namespace Drupal\oit\Plugin\Filter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Filter to remove any dashes in Cu Boulder text.
 */
#[Filter(
  id: "filter_cu",
  title: new TranslatableMarkup("CU Boulder Filter"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Remove hyphen in CU-Boulder"),
)]
class FilterCu extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $patterns = [];
    $patterns[0] = '/[\s](CU)-Boulder[\s]/';
    $patterns[1] = '/[\s](CU)-Boulder([^\s])/';
    $patterns[2] = '/(CU)-Boulder[\s]/';
    $patterns[3] = '/(CU)-Boulder/';
    $replacements = [];
    $replacements[0] = ' CU Boulder ';
    $replacements[1] = ' CU Boulder$2';
    $replacements[2] = 'CU Boulder ';
    $replacements[3] = 'CU Boulder';
    $cleaned = preg_replace($patterns, $replacements, $text);
    return new FilterProcessResult($cleaned);
  }

}
