<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oit\Plugin\BlockUuidQuery;
use Drupal\shortcode_svg\Plugin\ShortcodeIcon;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Front action links.
 *
 * @Block(
 *   id = "front_action_links",
 *   admin_label = @Translation("Front action links")
 * )
 */
class FrontActionLinks extends BlockBase implements
  ContainerFactoryPluginInterface {

  /**
   * Invoke renderer.
   *
   * @var \Drupal\oit\Plugin\BlockUuidQuery
   */
  protected $blockUuidQuery;

  /**
   * Invoke renderer.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityInterface;

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
      $container->get('oit.block.uuid.query'),
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
   * @param \Drupal\oit\Plugin\BlockUuidQuery $block_uuid_query
   *   Loads block.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_interface, BlockUuidQuery $block_uuid_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityInterface = $entity_interface;
    $this->blockUuidQuery = $block_uuid_query;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $view = Views::getView('service_alerts');
    $view->build('block_1');
    $count = $view->query->query()->countQuery()->execute()->fetchAssoc();
    $count = $count['expression'];
    $icon = new ShortcodeIcon();
    $render_query = $this->blockUuidQuery;
    $render_query->getBidByUuid('bb686d55-fe0c-41ef-8dd4-0257b0a7256a');
    $render = $render_query->loadBlock();
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="flex">
        <div class="flex-one-half">
        <ul class="frontactionlinks-links">
          <li{{ saColor }}><a class="icon button" href="/service-alerts">
              <span>{{ important | raw }}</span>
              {{ saText }}
              {{ count | raw }}
            </a></li>
          <li><a class="icon button" href="/node/24951">
              <span>{{ phone | raw }}</span>
              {{ reportText }}
            </a></li>
          <li><a class="icon button" href="/request-portal">
              <span>{{ check | raw }}</span>
              {{ requestText }}
            </a></li>
        </ul>
        </div>
        <div class="flex-one-half">
        <h2 class="h2"><span>{{ how }}</span></h2>
        {{ block }}
        </div>
      </div>',
      '#context' => [
        'saText' => $this->t('View service alert'),
        'saColor' => $count > 0 ? ' class=red' : "",
        'count' => $count > 0 ? "<span>$count</span>" : "",
        'reportText' => $this->t('Report an issue'),
        'requestText' => $this->t('Request Portal'),
        'how' => $this->t('How do I'),
        'block' => $render,
        'important' => $icon->setIcon('exclamation-stroke', 20, '#fff'),
        'phone' => $icon->setIcon('megaphone', 20, '#fff'),
        'check' => $icon->setIcon('bell-request', 20, '#fff'),
      ],
      '#cache' => [
        'tags' => [
          'node_type:service_alert',
        ],
      ],
    ];
  }

}
