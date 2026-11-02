<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\oit\Hook\MailHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the MailHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(MailHooks::class)]
#[CoversMethod(MailHooks::class, 'mailAlter')]
class MailHooksTest extends DrupalUnitTestCase {

  /**
   * Tests the sender headers are forced even when a From was already set.
   */
  public function testSenderHeadersForced(): void {
    $message = [
      'from' => 'oithelp@colorado.edu',
      'headers' => [
        'From' => 'oithelp@colorado.edu',
        'Sender' => 'oithelp@colorado.edu',
        'Return-Path' => 'oithelp@colorado.edu',
      ],
    ];
    (new MailHooks())->mailAlter($message);

    $this->assertSame('oitsrvcom@colorado.edu', $message['from']);
    $this->assertSame('Office of Information Technology <oitsrvcom@colorado.edu>', $message['headers']['From']);
    $this->assertSame('oitsrvcom@colorado.edu', $message['headers']['Sender']);
    $this->assertSame('oitsrvcom@colorado.edu', $message['headers']['Return-Path']);
  }

  /**
   * Tests Reply-To defaults when absent or empty.
   */
  public function testReplyToDefaults(): void {
    $message = [];
    (new MailHooks())->mailAlter($message);
    $this->assertSame('oithelp@colorado.edu', $message['headers']['Reply-To']);

    $message = ['headers' => ['Reply-To' => '']];
    (new MailHooks())->mailAlter($message);
    $this->assertSame('oithelp@colorado.edu', $message['headers']['Reply-To']);
  }

  /**
   * Tests an existing Reply-To is preserved.
   */
  public function testExistingReplyToPreserved(): void {
    $message = ['headers' => ['Reply-To' => 'no-reply@colorado.edu']];
    (new MailHooks())->mailAlter($message);
    $this->assertSame('no-reply@colorado.edu', $message['headers']['Reply-To']);
  }

  /**
   * Tests unrelated headers are left untouched.
   */
  public function testOtherHeadersUntouched(): void {
    $message = ['headers' => ['Content-Type' => 'text/html; charset=UTF-8']];
    (new MailHooks())->mailAlter($message);
    $this->assertSame('text/html; charset=UTF-8', $message['headers']['Content-Type']);
  }

}
