<?php

namespace Drupal\oit\Plugin\Filter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Filter dash out of Email text.
 */
#[Filter(
  id: "filter_email",
  title: new TranslatableMarkup("Email Filter"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Remove hyphen from E-mail"),
)]
class FilterEmail extends FilterBase {

  /**
   * {@inheritdoc}
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
