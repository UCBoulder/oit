<?php

namespace Drupal\oit\Plugin;

use Drupal\Core\Utility\Token;

/**
 * Domain helper functions.
 *
 * @BlockUuidQuery(
 *   id = "domain",
 *   title = @Translation("OIT Domain"),
 *   description = @Translation("Domain helper methods")
 * )
 */
class Domain {

  /**
   * Use token.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Construct object.
   */
  public function __construct(Token $token) {
    $this->token = $token;
  }

  /**
   * Get current domain.
   */
  public function getDomain() {
    $domainName = $this->token->replace('[domain:name]');
    $domain = 'na';
    if ($domainName == 'Office of Information Technology') {
      $domain = 'oit';
    }
    if ($domainName == 'Data &amp; Analytics') {
      $domain = 'oda';
    }
    return $domain;
  }

}
