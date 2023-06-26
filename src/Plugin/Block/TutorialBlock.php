<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tutorial Block.
 *
 * @Block(
 *   id = "tutorial_block",
 *   admin_label = @Translation("Tutorial os/layout block")
 * )
 */
class TutorialBlock extends BlockBase implements
  ContainerFactoryPluginInterface {

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

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
      $comp_type = $node->get('field_tut_comp_type_d7')
        ->getValue();
      $os_support = "";
      $os_list = [
        'MAC' => 'apple',
        'WINDOWS' => 'windows',
        'LINUX' => 'linuxregular',
        'ANDROID' => 'android',
        'IOS' => 'ios',
      ];
      if (!empty($comp_type)) {
        $os_support = "<strong>OS:</strong> ";
        $allowed_values = $node->get('field_tut_comp_type_d7')
          ->getSettings();
        foreach ($comp_type as $key) {
          $k = $key['value'][0];
          $comp_key = $allowed_values['allowed_values'][$k];
          $comp = $os_list[$comp_key];
          $icon = check_markup("[svg name=$comp width=25 color=000][/svg]", 'rich_text');
          $os_support .= "$icon ";
        }
      }
      return [
        '#type' => 'inline_template',
        '#template' => '<div class="flex">
        <div class="flex-one-half">{{ icon | raw }}</div>
        <div class="flex-one-half tutorial-layout">
        <dl class="tutorial-layout">
        <dt class="tutorial-layout--column"><strong>{{ layout }}:</strong></dt>
        <dd class="tutorial-layout--one">{{ onecol | raw }}</dd>
        <dd class="tutorial-layout--two">{{ twocol | raw }}</dd>
        </div>
        </div>',
        '#context' => [
          'icon' => $os_support,
          'layout' => $this->t('Layout'),
          'onecol' => check_markup("[svg name=onecol alt='one column' width=25 color=000][/svg]", 'rich_text'),
          'twocol' => check_markup("[svg name=twocol alt='two columns' width=25 color=000][/svg]", 'rich_text'),
        ],
      ];
    }
    return '';
  }

  /**
   * Set cache tag by node id.
   */
  public function getCacheTags() {
    // With this when your node change your block will rebuild.
    if ($node = $this->routMatchInterface->getParameter('node')) {
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
    // If you depends on \Drupal::routeMatch()
    // you must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
