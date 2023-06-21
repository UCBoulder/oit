<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Absolute link shame Block.
 *
 * @Block(
 *   id = "absolute_link_shame",
 *   admin_label = @Translation("Shame user for using absolute links")
 * )
 */
class AbsoluteLinkShame extends BlockBase implements
  ContainerFactoryPluginInterface {

  /**
   * Invoke renderer.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityInterface;

  /**
   * Invoke renderer.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routMatchInterface;

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
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_interface
   *   Invokes renderer.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match_interface
   *   Invokes routeMatch.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_interface, RouteMatchInterface $route_match_interface) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityInterface = $entity_interface;
    $this->routMatchInterface = $route_match_interface;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $thisNode = $this->routMatchInterface->getParameter('node');
    if ($thisNode instanceof NodeInterface) {
      $nid = $thisNode->id();
      $node = $this->entityInterface->getStorage('node')->load($nid);
      $body = $node->get('body')->getString();
      if (isset($body)) {
        preg_match('/\=[\"\']http[s]*\:\/\/oit.colorado.edu/', $body, $matches1, PREG_OFFSET_CAPTURE, 3);
        preg_match('/\=[\"\']http[s]*\:\/\/w*.?colorado.edu\/oit/', $body, $matches2, PREG_OFFSET_CAPTURE, 3);
        $match = !empty($matches1) || !empty($matches2) ? TRUE : FALSE;
        if ($match) {
          $fail_img = [
            'http://i.giphy.com/pcC2u7rl89b44.gif',
            'https://media.giphy.com/media/EimNpKJpihLY4/giphy.gif',
            'https://media.giphy.com/media/ToMjGpBmDyMmBrMmbf2/giphy.gif',
            'https://media.giphy.com/media/d1tlu8P8b5FkY/giphy.gif',
            'https://media.giphy.com/media/RcZiNH8v6Kt8c/giphy.gif',
            'https://media.giphy.com/media/gjNwWERp7JeM0/giphy.gif',
            'https://media.giphy.com/media/vXqslSRLeAP4s/giphy.gif',
            'https://media.giphy.com/media/4z0secN26LBiE/giphy.gif',
            'https://media.giphy.com/media/vX9WcCiWwUF7G/giphy.gif',
            'https://media.giphy.com/media/13lTgtSUmqMrlu/giphy.gif',
            'https://media.giphy.com/media/JKswczDIOEG2Y/giphy.gif',
            'https://media.giphy.com/media/mUOdJLFQeHAf6/giphy.gif',
          ];
          $fail_img_rand = array_rand($fail_img, 1);
          return [
            '#type' => 'inline_template',
            '#template' => "<div class='banner banner--specific'><strong>{{ message }}</strong>  <br /><br /> <img src='{{ fail_img }}' alt='Much Shame on using absolute links' style='margin: 0 auto;' /></div>",
            '#context' => [
              'message' => $this->t('There was a failure to use relative links on this page. Please update the offending links and/or images.'),
              'fail_img' => $fail_img[$fail_img_rand],
            ],
          ];
        }
      }
      return [
        '#markup' => '',
      ];
    }
    return [
      '#markup' => '',
    ];
  }

  /**
   * Return no cache.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
