<?php

namespace Drupal\oit\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filter to remove any dashes in Cu Boulder text.
 *
 * @Filter(
 *   id = "filter_cu",
 *   title = @Translation("CU Boulder Filter"),
 *   description = @Translation("Remove hyphen in CU-Boulder"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterCu extends FilterBase {

  /**
   * Process filter to replace dashes.
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
