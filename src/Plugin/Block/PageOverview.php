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
 * Page Overview Links.
 *
 * @Block(
 *   id = "page_overview",
 *   admin_label = @Translation("Page Overview Block")
 * )
 */
class PageOverview extends BlockBase implements
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
      $content = $node->get('body')->getValue();
      $context = [
        'summary' => isset($content[0]['summary']) ? $this->processedText($content[0]['summary']) : '',
        'os' => '',
        'download_url' => '',
        'download_icon' => '',
        'download_text' => $this->t('Access Software'),
        'download_class' => 'ml-auto',
        'affiliation' => '',
      ];
      // Add this to phish category.
      if ($node->getType() == 'page') {
        if (!empty($comp_type = $node->get('field_tut_comp_type_d7')->getValue())) {
          $icon_key = [
            68 => 'sw-faculty',
            71 => 'shake',
            70 => 'workatdesk',
            69 => 'sw-student',
            0 => 'apple',
            1 => 'windows',
            2 => 'linuxregular',
            3 => 'android',
            4 => 'ios',
          ];
          $set_comp_type = 'OS: ';
          foreach ($comp_type as $os) {
            $set_os = $icon_key[$os['value']];
            // Translates linux and apple and adds uppercase first letter.
            $alt = ucfirst(
              ($set_os == 'linuxregular' ? 'Linux' : ($set_os == 'apple' ? 'MacOS' : $set_os))
            );
            $set_comp_type .= isset($icon_key[$os['value']]) ? "[svg name=" . $icon_key[$os['value']] . " alt ='$alt' width=25 color=000][/svg] " : '';
          }
          $context['os'] = $this->processedText($set_comp_type);

          // Download link if set.
          // Return full external url or /node/# for internal links.
          if ($node->field_software_download_link->get(0) !== NULL) {
            if ($node->field_software_download_link->get(0)->isExternal()) {
              $context['download_url'] = $node->field_software_download_link->get(0)->getString();
            }
            else {
              $context['download_url'] = $node->field_software_download_link->get(0)->getUrl()->toString();
            }
            $context['download_icon'] = $this->processedText("[svg name=download width=25 color=0073E6][/svg]");
            $context['download_class'] = $node->get('taxonomy_vocabulary_11')->getValue() ? 'm-auto' : 'ml-auto';
          }

          // Setup affiliations.
          if ($affiliation = $node->get('taxonomy_vocabulary_11')->getValue()) {
            $set_affiliation = 'Affiliation: ';
            foreach ($affiliation as $aff) {
              $term_name = $this->entityInterface->getStorage('taxonomy_term')->load($aff['target_id'])->get('name')->value;
              $set_affiliation .= isset($icon_key[$aff['target_id']]) ? "[svg name=" . $icon_key[$aff['target_id']] . " alt='$term_name' width=25 color=000][/svg] " : '';
            }
            $context['affiliation'] = $this->processedText($set_affiliation);
          }
        }
      }
      return [
        '#type' => 'inline_template',
        '#template' => '{{ summary }}{% if os %}<div class="flex software-extra">' .
        '<div class="flex-one-third">{{ os }}</div>' .
        '{% if download_url %}<div class="flex-one-third"><div class="{{ download_class }}">' .
        '<a href="{{ download_url }}" class="text-uppercase">{{ download_text }}&nbsp; {{ download_icon }}</a>' .
        '</div></div>{% endif %}' .
        '{% if affiliation %}<div class="flex-one-third"><div class="ml-auto">{{ affiliation }}</div></div>{% endif %}' .
        '</div>{% endif %} ',
        '#context' => $context,
      ];
    }
    return [];
  }

  /**
   * Builds a renderable array for filtered text.
   *
   * @param string $text
   *   The raw text to run through the text format pipeline.
   * @param string $format
   *   The machine name of the text format to apply.
   *
   * @return array
   *   A processed_text render array.
   */
  protected function processedText($text, $format = 'rich_text') {
    return [
      '#type' => 'processed_text',
      '#text' => $text,
      '#format' => $format,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // With this, when your node changes, your block will rebuild.
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
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // If you depend on \Drupal::routeMatch()
    // you must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
