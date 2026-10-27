<?php

namespace Drupal\Tests\oit\Unit\Services;

use Drupal\oit\Plugin\Domain;
use Drupal\oit\Services\DomainFilterRedirectSubscriber;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Unit tests for DomainFilterRedirectSubscriber.
 */
#[Group('oit')]
#[CoversClass(DomainFilterRedirectSubscriber::class)]
class DomainFilterRedirectSubscriberTest extends DrupalUnitTestCase {

  /**
   * Runs the subscriber against a request and returns the event.
   *
   * @param string $domain
   *   The site name Domain::getDomain() returns.
   * @param string $uri
   *   The request URI.
   * @param string|null $route
   *   The matched route name.
   * @param string $method
   *   The HTTP method.
   * @param int $type
   *   The kernel request type.
   *
   * @return \Symfony\Component\HttpKernel\Event\RequestEvent
   *   The event after the subscriber has run.
   */
  protected function dispatch(string $domain, string $uri, ?string $route, string $method = 'GET', int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent {
    $domain_service = $this->createMock(Domain::class);
    $domain_service->method('getDomain')->willReturn($domain);

    $request = Request::create($uri, $method);
    $request->attributes->set('_route', $route);
    $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $type);

    (new DomainFilterRedirectSubscriber($domain_service))->onRequest($event);

    return $event;
  }

  /**
   * Data provider for testRedirects().
   *
   * @return array
   *   Rows of [domain, uri, route, expected redirect URL].
   */
  public static function redirectProvider(): array {
    $content = 'system.admin_content';
    $moderated = 'content_moderation.admin_moderated_content';
    $oit = 'field_domain_access_target_id=oit_colorado_edu';
    $oda = 'field_domain_access_target_id=oda_colorado_edu';
    return [
      'oit content' => ['oit', '/admin/content', $content, "/admin/content?$oit"],
      'oda content' => ['oda', '/admin/content', $content, "/admin/content?$oda"],
      'oit moderated' => ['oit', '/admin/content/moderated', $moderated, "/admin/content/moderated?$oit"],
      'oda moderated' => ['oda', '/admin/content/moderated', $moderated, "/admin/content/moderated?$oda"],
      'oda moderated view route' => [
        'oda',
        '/admin/content/moderated',
        'view.moderated_content.moderated_content',
        "/admin/content/moderated?$oda",
      ],
      'keeps existing query' => ['oit', '/admin/content?page=2', $content, "/admin/content?page=2&$oit"],
    ];
  }

  /**
   * Tests listings without the filter are redirected with it added.
   */
  #[DataProvider('redirectProvider')]
  public function testRedirects(string $domain, string $uri, string $route, string $expected): void {
    $response = $this->dispatch($domain, $uri, $route)->getResponse();

    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertSame($expected, $response->getTargetUrl());
  }

  /**
   * Data provider for testNoRedirect().
   *
   * @return array
   *   Rows of [domain, uri, route, method, request type].
   */
  public static function noRedirectProvider(): array {
    $main = HttpKernelInterface::MAIN_REQUEST;
    $sub = HttpKernelInterface::SUB_REQUEST;
    $content = 'system.admin_content';
    return [
      'filter set to other' => ['oit', '/admin/content?field_domain_access_target_id=oda', $content, 'GET', $main],
      'filter set to all' => ['oit', '/admin/content?field_domain_access_target_id=All', $content, 'GET', $main],
      'unknown domain' => ['na', '/admin/content', $content, 'GET', $main],
      'other route' => ['oit', '/node/1', 'entity.node.canonical', 'GET', $main],
      'no route' => ['oit', '/admin/content', NULL, 'GET', $main],
      'post' => ['oit', '/admin/content', $content, 'POST', $main],
      'sub request' => ['oit', '/admin/content', $content, 'GET', $sub],
    ];
  }

  /**
   * Tests requests that must not be redirected.
   */
  #[DataProvider('noRedirectProvider')]
  public function testNoRedirect(string $domain, string $uri, ?string $route, string $method, int $type): void {
    $this->assertNull($this->dispatch($domain, $uri, $route, $method, $type)->getResponse());
  }

}
