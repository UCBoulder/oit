<?php

namespace Drupal\oit\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\State;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\shortcode_svg\Plugin\ShortcodeIcon;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller routines for zap routes.
 */
class AbuseController extends ControllerBase {
  use StringTranslationTrait;

  /**
   * State service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The abuse list.
   *
   * @var array
   */
  protected $abuseList;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Object used to get request data, such as the hash.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Object used to get request data, such as the hash.
   *
   * @var \Drupal\oit\Plugin\BlockUuidQuery
   */
  protected $blockUuidQuery;

  /**
   * Object used to get request data, such as the hash.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The 'renderer' service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The ModuleExtensionList to be passed to the config importer.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Call shortcode svg icon.
   *
   * @var \Drupal\shortcode_svg\Plugin\ShortcodeIcon
   */
  protected $shortcodeSvgIcon;

  /**
   * Constructs a new AbuseController object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\shortcode_svg\Plugin\ShortcodeIcon $shortcode_svg_icon
   *   The shortcode SVG icon service.
   * @param \Drupal\Core\State\State $state
   *   The state service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    AccountInterface $account,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactory $config_factory,
    ModuleExtensionList $module_extension_list,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_type_manager,
    ShortcodeIcon $shortcode_svg_icon,
    State $state,
    Connection $database,
  ) {
    $this->account = $account;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->moduleExtensionList = $module_extension_list;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->shortcodeSvgIcon = $shortcode_svg_icon;
    $this->state = $state;
    $this->database = $database;

    $abuse = $this->state->get('ban_ip_questionable');
    $this->abuseList = json_decode($abuse, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('extension.list.module'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('shortcode_svg.icon'),
      $container->get('state'),
      $container->get('database')
    );
  }

  /**
   * Display the abuse IP table.
   *
   * @return array
   *   Render array for the abuse IP table.
   */
  public function abuseIpTable() {
    $rows = [];
    if (!empty($this->abuseList)) {
      foreach ($this->abuseList as $key => $row) {
        if (!array_key_exists('score', $row) || !array_key_exists('country', $row)) {
          continue;
        }
        if (empty($key) || $row['score'] === NULL || $row['country'] === NULL) {
          continue;
        }
        // Link to AbuseIPDB for each IP.
        $url = Url::fromUri('https://www.abuseipdb.com/check/' . $key, [
          'attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ],
        ]);
        $link = Link::fromTextAndUrl($key, $url);

        // Url to 'keep/$key'.
        $keep_url = Url::fromRoute('oit.abusekeep', ['ip' => $key], [
          'attributes' => [
            'class' => ['button', 'button--primary'],
            'rel' => 'nofollow',
          ],
        ]);
        $keep_link = Link::fromTextAndUrl('Yes', $keep_url);
        $keep_link = $keep_link->toString();

        // Url to 'keep/$key'.
        $nokeep_url = Url::fromRoute('oit.abusenokeep', ['ip' => $key], [
          'attributes' => [
            'class' => ['button', 'button--secondary'],
            'rel' => 'nofollow',
          ],
        ]);
        $nokeep_link = Link::fromTextAndUrl('No', $nokeep_url);
        $nokeep_link = $nokeep_link->toString();

        // Url to 'keep/$key'.
        $whitelist_url = Url::fromRoute('oit.abusewhitelist', ['ip' => $key], [
          'attributes' => [
            'class' => ['button', 'button--secondary'],
            'rel' => 'nofollow',
          ],
        ]);
        $whitelist_link = Link::fromTextAndUrl('Whitelist', $whitelist_url);
        $whitelist_link = $whitelist_link->toString();

        $rows[] = [
          Markup::create($link->toString()),
          $row['score'],
          $row['country'],
          Markup::create($keep_link . $nokeep_link . $whitelist_link),
        ];
      }
    }

    $header = [
      'ip' => $this->t('IP'),
      'score' => $this->t('Confidence Score'),
      'country' => $this->t('Country'),
      'keep' => 'Keep Banned?',
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content has been found.'),
    ];

    return $build;
  }

}
