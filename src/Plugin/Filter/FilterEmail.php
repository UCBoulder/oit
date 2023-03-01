<?php

namespace Drupal\oit\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filter dash out of Email text.
 *
 * @Filter(
 *   id = "filter_email",
 *   title = @Translation("Email Filter"),
 *   description = @Translation("Remove hyphen from E-mail"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterEmail extends FilterBase {

  /**
   * Search and replace all dashes in Email.
   */
  public function process($text, $langcode) {
    $patterns = [];
    $patterns[0] = '/[\s]([eE])-[mM]ail[\s]/';
    $patterns[1] = '/[\s]([eE])-[mM]ail([^\s])/';
    $patterns[2] = '/([eE])-[mM]ail[\s]/';
    $replacements = [];
    $replacements[0] = ' $1mail ';
    $replacements[1] = ' $1mail$2';
    $replacements[2] = '$1mail ';
    $cleaned = preg_replace($patterns, $replacements, $text);
    return new FilterProcessResult($cleaned);
  }

}
