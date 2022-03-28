<?php

namespace Drupal\oit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Top pages block.
 *
 * @Block(
 *   id = "menu_anchor",
 *   admin_label = @Translation("Menu anchor link")
 * )
 */
class MenuAnchor extends BlockBase implements
    ContainerFactoryPluginInterface {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   */
  protected $themeHandler;

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme_handler')
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
   * @param \\Drupal\Core\Extension\ThemeHandler $theme_handler
   *   The theme manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeHandler $theme_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $icon = '<svg
    viewBox="0 0 50 40"
    role="presentation"
    focusable="false"
    aria-label="' . $this->t('Menu Icon') . '">
    <line class="hm-top" x1="5%" x2="95%" y1="5%" y2="5%" />
    <line class="hm-middle" x1="5%" x2="95%" y1="50%" y2="50%" />
    <line class="hm-bottom" x1="5%" x2="95%" y1="95%" y2="95%" />
  </svg>';
    $block = sprintf(
      "<span class='seperator'>|</span><a class='menu-anchor' title='%s' href='%s'>%s <span class='menu-text'>%s</span></a>",
      $this->t('Menu'),
      '#off-canvas',
      $icon,
      $this->t('Menu')
    );
    return [
      '#markup' => $block,
      '#allowed_tags' => ['a', 'span', 'svg', 'line'],
    ];
  }

}
