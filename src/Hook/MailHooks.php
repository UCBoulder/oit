<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for outgoing mail.
 */
class MailHooks {

  /**
   * Implements hook_mail_alter().
   *
   * Forces the From address on every outgoing message to the OIT service
   * account (oitsrvcom@colorado.edu). Office 365 rejects mail whose From does
   * not match an authorized sender (SendAsDenied), so this reproduces the
   * From-rewriting the drupal/smtp module performed before the migration to
   * Symfony Mailer Lite. Symfony Mailer Lite has no transport-level From
   * override, so the header is forced here, after hook_mail() has run and
   * before the message is sent.
   *
   * Reply-To defaults to oithelp@colorado.edu so replies still reach the help
   * desk, but any Reply-To a module set explicitly is preserved.
   *
   * @param array $message
   *   The mail message array, passed by reference.
   */
  #[Hook('mail_alter')]
  public function mailAlter(array &$message): void {
    $from = 'Office of Information Technology <oitsrvcom@colorado.edu>';
    $message['from'] = 'oitsrvcom@colorado.edu';
    $message['headers']['From'] = $from;
    $message['headers']['Sender'] = 'oitsrvcom@colorado.edu';
    $message['headers']['Return-Path'] = 'oitsrvcom@colorado.edu';
    if (empty($message['headers']['Reply-To'])) {
      $message['headers']['Reply-To'] = 'oithelp@colorado.edu';
    }
  }

}
