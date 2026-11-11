<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Hook implementations for generic preprocessing.
 */
class PreprocessHooks {

  /**
   * Constructs a new PreprocessHooks object.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path stack.
   */
  public function __construct(
    protected ThemeManagerInterface $themeManager,
    protected CurrentPathStack $currentPath,
  ) {}

  /**
   * Implements hook_preprocess().
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   * @param string $hook
   *   The name of the theme hook being preprocessed.
   */
  #[Hook('preprocess')]
  public function preprocess(array &$variables, $hook): void {
    $theme = $this->themeManager->getActiveTheme()->getName();
    if ($theme == 'gin') {
      $form_id = $variables['form']['#id'] ?? '';
      $current_path = $this->currentPath->getPath();
      if ($current_path == '/node/add') {
        $variables['#attached']['library'][] = 'oit/gingerbread';
      }
      if ($form_id == 'node-tutorial-form' || $form_id == 'node-tutorial-edit-form') {
        $variables['#attached']['library'][] = 'oit/gin_select';
      }
    }
  }

}
