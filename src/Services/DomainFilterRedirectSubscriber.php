<?php

namespace Drupal\oit\Services;

use Drupal\oit\Plugin\Domain;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects admin content listings to filter on the current site's domain.
 *
 * On the oit and oda sites, the content and moderated content listings get
 * field_domain_access_target_id added to the query string when it is missing,
 * so editors only see content for the site they are on.
 */
class DomainFilterRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Routes that get the domain filter.
   *
   * The moderated content view overrides the content_moderation route and
   * keeps its name; the view route is listed too in case that changes.
   */
  const ROUTES = [
    'system.admin_content',
    'content_moderation.admin_moderated_content',
    'view.moderated_content.moderated_content',
  ];

  /**
   * Domain ids keyed by the site name from Domain::getDomain().
   */
  const DOMAIN_IDS = [
    'oit' => 'oit_colorado_edu',
    'oda' => 'oda_colorado_edu',
  ];

  /**
   * Constructs a new DomainFilterRedirectSubscriber object.
   *
   * @param \Drupal\oit\Plugin\Domain $domain
   *   The OIT domain service.
   */
  public function __construct(
    protected Domain $domain,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Runs after routing and access checks (RouterListener, priority 32).
    $events[KernelEvents::REQUEST][] = ['onRequest', 0];
    return $events;
  }

  /**
   * Redirects to the same page with the domain filter set.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !$request->isMethodSafe()) {
      return;
    }

    if (!in_array($request->attributes->get('_route'), self::ROUTES, TRUE)) {
      return;
    }

    if ($request->query->has('field_domain_access_target_id')) {
      return;
    }

    $domain_id = self::DOMAIN_IDS[$this->domain->getDomain()] ?? NULL;
    if (!$domain_id) {
      return;
    }

    $query = $request->query->all();
    $query['field_domain_access_target_id'] = $domain_id;
    $url = $request->getBaseUrl() . $request->getPathInfo() . '?' . http_build_query($query);
    $event->setResponse(new RedirectResponse($url));
  }

}
