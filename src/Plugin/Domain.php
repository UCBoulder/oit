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
   * Constructs a new Domain object.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(Token $token) {
    $this->token = $token;
  }

  /**
   * Get current domain machine name.
   *
   * @return string
   *   The domain identifier: 'oit', 'oda', or 'na'.
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
