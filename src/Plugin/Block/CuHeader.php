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
 *   id = "cu_header",
 *   admin_label = @Translation("CU svg logo link")
 * )
 */
class CuHeader extends BlockBase implements
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
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
    $theme_path = $this->themeHandler->getTheme('dingo')->getPath();
    $block = sprintf(
      "<a href='%s'><img src='/%s/%s' alt='%s' class='culogo'></a>",
      'https://www.colorado.edu',
      $theme_path,
      'images/cuboulder.svg',
      $this->t('University of Colorado Boulder')
    );
    return [
      '#markup' => $block,
    ];
  }

}
