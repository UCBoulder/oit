<?php

namespace Drupal\oit\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oit\Plugin\BlockUuidQuery;
use Drupal\oit\Plugin\ServiceHealth;
use Drupal\shortcode_svg\Plugin\ShortcodeIcon;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller routines for zap routes.
 */
class OitController extends ControllerBase {

  /**
   * Object used to get request data, such as the hash.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

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
   * Access date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  private $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs request stuff.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Interact with Private temporary storage.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Access to the current request, including to session objects.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Interact with Private temporary storage.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The page cache kill switch service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Interact with config factory.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   Interact with date formatter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Load entity.
   */
  public function __construct(
    AccountInterface $account,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory,
    KillSwitch $killSwitch,
    ConfigFactory $config_factory,
    DateFormatter $date_formatter,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->account = $account;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory->get('oit');
    $this->killSwitch = $killSwitch;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $teamsAlert = $container->get('oit.teamsalert');
    return new static(
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('page_cache_kill_switch'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $teamsAlert
    );
  }

  /**
   * Routes for zap.
   */
  public function oitDenied() {
    $content = $this->deniedContent();

    return [
      '#markup' => $content,
      '#attached' => [
        'library' => [
          'oit/oit_pass',
        ],
      ],
    ];
  }

  /**
   * Build content to display on page.
   */
  private function deniedContent() {
    if ($_SERVER["REQUEST_URI"]) {
      $clean_uri = Xss::filter($_SERVER["REQUEST_URI"]);
      $requested_path = '?destination=' . $clean_uri;
    }
    else {
      $requested_path = '';
    }
    global $base_url;
    $module_path = \Drupal::service('extension.list.module')->getPath('oit');
    $content = sprintf(
      '<p>%s <a href="%s/saml_login%s">%s</a> %s.</p><a style="border: none;" href="%s/saml_login%s"><img src="%s/%s/images/you_shall_not_pass.png" alt="%s" title="%s" style="display:none;" id="myprecious" /></a>',
      $this->t('You may need to'),
      $base_url,
      $requested_path,
      $this->t('login'),
      $this->t('in order to see this page'),
      $base_url,
      $requested_path,
      $base_url,
      $module_path,
      $this->t('One Image to rule them all'),
      $this->t('The CU Buffalo meets Gandalf and must provide his Identikey in order to continue along his path.')
    );
    return $content;
  }

  /**
   * Create page to forward user to their profile.
   */
  public function oitUserEdit() {
    $nid = $this->account->id();
    $path = Url::fromRoute('entity.user.edit_form', ['user' => $nid])->toString();

    $response = new RedirectResponse($path);
    $response->send();
    exit;
  }

  /**
   * Blank front page.
   */
  public function front() {
    return [
      '#markup' => '',
      '#attached' => [
        'library' => [
          'oit/oit_pass',
        ],
      ],
    ];
  }

  /**
   * Request Portal page.
   */
  public function requestPortal() {
    $svg = new ShortcodeIcon();
    $title = $this->t('Request Portal');
    $search = '<form action="/search/cse" method="get" id="search-request" accept-charset="UTF-8" class="search" data-drupal-form-fields="edit-keys"><label for="edit-keys" class="visually-hidden form-item__label">Search</label> <input title="Enter the terms you wish to search for." placeholder="Get help with..." autocomplete="off" type="search" id="search-keys" name="keys" value="" size="15" maxlength="128" class="form-search form-item__textfield"><input data-drupal-selector="submit-search" type="submit" id="submit-search" value="Search" class="button"></form>';
    $issue_query = new BlockUuidQuery('04d5cc3e-8b9d-4bb7-8cab-16f162cb729a');
    $issue = $issue_query->loadBlock();
    $request_query = new BlockUuidQuery('3a69be06-3b81-4a30-9996-0da3d6c45e8d');
    $request = $request_query->loadBlock();
    $cases_query = new BlockUuidQuery('f4bc74b5-ba88-4b86-86e4-58d9ce96260b');
    $my_cases = $cases_query->loadBlock();

    $topreq_query = new BlockUuidQuery('9c8e8f57-f654-4976-a4bd-85acb21f0457');
    $topreq = $topreq_query->loadBlock();
    $view_id = 'request_portal';
    $display = 'block_1';
    $mc_title = $this->t('Messaging and Collaboration') . ' ' . $svg->setIcon('email', '60', '#000');
    $mc = $this->requestPortalView($view_id, $display, ['857'], '');
    $tla_title = $this->t('Teaching and Learning Apps') . ' ' . $svg->setIcon('electronicharmony', '60', '#000');
    $tla = $this->requestPortalView($view_id, $display, ['875'], '');
    $ia_title = $this->t('Identity and Accounts') . ' ' . $svg->setIcon('techsupport', '60', '#000');
    $ia = $this->requestPortalView($view_id, $display, ['861'], '');
    $ni_title = $this->t('Network and Internet') . ' ' . $svg->setIcon('radiowaves', '60', '#000');
    $ni = $this->requestPortalView($view_id, $display, ['865'], '');
    $lst_title = $this->t('Learning Spaces Technology') . ' ' . $svg->setIcon('laptop', '60', '#000');
    $lst = $this->requestPortalView($view_id, $display, ['873'], '');

    $page['string'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="request-header"><h1>{{ title }}</h1>
          {{ search|raw }}
          <div class="flex">
            <div class="report flex-one-third">{{ issue }}</div>
            <div class="request flex-one-third">{{ request }}</div>
            <div class="view flex-one-third">{{ cases }}</div>
          </div>
        </div>
        <div class="request-webform-lists flex">
          <div class="text-long flex-one-third">{{ top }}</div>
          <div class="text-long flex-one-third"><h3>{{ messaging_title|raw }}</h3>{{ messaging }}</div>
          <div class="text-long flex-one-third"><h3>{{ teachlearn_title|raw }}</h3>{{ teachlearn }}</div>
          <div class="text-long flex-one-third"><h3>{{ identity_title|raw }}</h3>{{ identity }}</div>
          <div class="text-long flex-one-third"><h3>{{ network_title|raw }}</h3>{{ network }}</div>
          <div class="text-long flex-one-third"><h3>{{ learning_title|raw }}</h3>{{ learning }}</div>
        </div>',
      '#context' => [
        'title' => $title,
        'search' => $search,
        'issue' => $issue,
        'request' => $request,
        'top' => $topreq,
        'cases' => $my_cases,
        'messaging_title' => $mc_title,
        'messaging' => $mc,
        'teachlearn_title' => $tla_title,
        'teachlearn' => $tla,
        'identity_title' => $ia_title,
        'identity' => $ia,
        'network_title' => $ni_title,
        'network' => $ni,
        'learning_title' => $lst_title,
        'learning' => $lst,
      ],
    ];

    return [
      '#markup' => render($page),
      '#allowed_tags' => ['a', 'form', 'label', 'input', 'svg', 'use'],
    ];
  }

  /**
   * Pull request portal block with contextual filter.
   */
  private function requestPortalView($view, $display, $arg, $title) {
    // Firstly, get the view in question.
    $view = Views::getView($view);
    // Set which view display we want.
    $view->setDisplay($display);
    // Pass any arguments that the view display requires.
    $view->setArguments($arg);
    // Execute the view.
    $view->execute();
    $view_result = $view->result;
    // Access field data from the view results.
    $n = 0;
    foreach ($view_result as $row) {
      foreach ($view->field as $field) {
        $r[$n]['#markup'] = '<span class="truncate">' . $field->advancedRender($row)->__toString() . '</span>';
      }
      $n++;
    }
    $list = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $title,
      '#items' => $r,
      '#attributes' => ['class' => 'mylist'],
      '#wrapper_attributes' => ['class' => 'container'],
    ];
    return $list;
  }

  /**
   * Service Alert dashboard page.
   */
  public function serviceAlertHealth() {
    $service_dashboard = new ServiceHealth();
    $category = $service_dashboard->serviceHealthLookup();
    $status_key = $service_dashboard->serviceHealthStatusByKey();
    $clean_category = $service_dashboard->removeDuplicates($category);
    foreach ($clean_category as $st) {
      $cat_key = $st['key'];
      $cat_status = $category[$cat_key]['status'];
      $status_current = $status_key[$cat_status];
      $svg = $service_dashboard->statusCircle($cat_status);
      $status_row = "$svg $status_current";
      $rows[] = [
        $service_dashboard->serviceLink($category[$cat_key]['service']),
        check_markup($status_row, 'rich_text'),
        $category[$cat_key]['button'],
      ];
    }
    $header = [
      'title' => $this->t('Service'),
      'status' => $this->t('Status'),
      'Service Alert' => $this->t('Service Alert'),
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content has been found.'),
      '#cache' => [
        'tags' => [
          'node_type:service_alert',
        ],
      ],
    ];

    return [
      '#markup' => render($build),
      '#allowed_tags' => ['svg', 'circle'],
    ];
  }

}
