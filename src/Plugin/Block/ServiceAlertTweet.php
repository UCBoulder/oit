<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service Alert Tweet block.
 *
 * @Block(
 *   id = "sa_tweet",
 *   admin_label = @Translation("Service Alert Tweet")
 * )
 */
class ServiceAlertTweet extends BlockBase implements
  ContainerFactoryPluginInterface {

  /**
   * Current path injected.
   *
   * @var current_path\Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPathStack;

  /**
   * Request injected.
   *
   * @var request\Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Route Match injected.
   *
   * @var routeMatchInterface\Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatchInterface;

  /**
   * Entity Manager injected.
   *
   * @var entityTypeManager\Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container pulled in.
   * @param array $configuration
   *   Configuration added.
   * @param string $plugin_id
   *   Plugin_id added.
   * @param mixed $plugin_definition
   *   Plugin_definition added.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param mixed $plugin_definition
   *   Plugin Definition mixed.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   Pull current path.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route Match.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity Type Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentPathStack $current_path, RequestStack $request_stack, RouteMatchInterface $route_match, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentPathStack = $current_path;
    $this->request = $request_stack;
    $this->routeMatchInterface = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_match = $this->routeMatchInterface->getRawParameters()->getIterator();
    $node_title = $this->entityTypeManager->getStorage('node')->load($route_match['node'])->getTitle();
    $node_type = $this->entityTypeManager->getStorage('node')->load($route_match['node'])->bundle();
    $service_status = $node_type == 'service_alert' ? $this->entityTypeManager->getStorage('node')->load($route_match['node'])->get('field_service_alert_status')->value : '';
    $host = $this->request->getCurrentRequest()->getSchemeAndHttpHost();
    $current_path = $this->currentPathStack->getPath();
    $tweet = urlencode($service_status . ': ' . $node_title . ' ' . $host . $current_path);
    $url = Url::fromUri('https://x.com/intent/tweet?text=' . $tweet);
    $external_link = Link::fromTextAndUrl($this->t('𝕏'), $url)->toString();
    return [
      '#markup' => $external_link,
    ];
  }

  /**
   * Set cache tag by node id.
   */
  public function getCacheTags() {
    // With this when your node change your block will rebuild.
    if ($node = $this->routeMatchInterface->getParameter('node')) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  /**
   * Return cache contexts.
   */
  public function getCacheContexts() {
    // If you depend on \Drupal::routeMatch()
    // you must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
