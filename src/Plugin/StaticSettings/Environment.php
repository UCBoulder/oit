<?php

declare(strict_types=1);

namespace Drupal\oit\Plugin\StaticSettings;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\static_setting_contexts\Attribute\StaticSettings;
use StaticSettings\BaseStaticSettingInterface;

/**
 * Enum class for custom static settings.
 */
#[StaticSettings(
  id: 'enum_environment',
  label: new TranslatableMarkup('Environment Setting'),
  description: new TranslatableMarkup('This setting defines the environment in which the application is running. It can be used to conditionally load configurations or features based on the environment. The available options are: development, staging, and production.'),
)]
enum Environment: string implements BaseStaticSettingInterface {
  case Local = 'local';
  case Development = 'development';
  case Staging = 'staging';
  case Production = 'production';
}
