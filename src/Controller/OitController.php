<?php

namespace Drupal\oit\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\RendererInterface;
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
   * Access date formatter.
   *
   * @var \Drupal\oit\Plugin\ServiceHealth
   */
  private $serviceHealth;

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
   * Constructs request stuff.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Interact with Private temporary storage.
   * @param \Drupal\oit\Plugin\BlockUuidQuery $block_uuid_query
   *   Get block uuid.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Access to the current request, including to session objects.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Interact with Private temporary storage.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The page cache kill switch service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Interact with config factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   Load module extension list.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The 'renderer' service.
   * @param \Drupal\oit\Plugin\ServiceHealth $service_health
   *   Load service health.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Load entity.
   * @param \Drupal\shortcode_svg\Plugin\ShortcodeIcon $shortcode_svg_icon
   *   Call shortcode svg icon.
   */
  public function __construct(
    AccountInterface $account,
    BlockUuidQuery $block_uuid_query,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory,
    KillSwitch $killSwitch,
    ConfigFactory $config_factory,
    ModuleExtensionList $module_extension_list,
    RendererInterface $renderer,
    ServiceHealth $service_health,
    EntityTypeManagerInterface $entity_type_manager,
    ShortcodeIcon $shortcode_svg_icon,
  ) {
    $this->account = $account;
    $this->requestStack = $request_stack->getCurrentRequest();
    $this->blockUuidQuery = $block_uuid_query;
    $this->loggerFactory = $logger_factory->get('oit');
    $this->killSwitch = $killSwitch;
    $this->configFactory = $config_factory;
    $this->moduleExtensionList = $module_extension_list;
    $this->renderer = $renderer;
    $this->serviceHealth = $service_health;
    $this->entityTypeManager = $entity_type_manager;
    $this->shortcodeSvgIcon = $shortcode_svg_icon;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('oit.block.uuid.query'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('page_cache_kill_switch'),
      $container->get('config.factory'),
      $container->get('extension.list.module'),
      $container->get('renderer'),
      $container->get('oit.servicehealth'),
      $container->get('entity_type.manager'),
      $container->get('shortcode_svg.icon')
    );
  }

  /**
   * Custom 404 page.
   */
  public function oit404() {
    // Get path to oit module.
    $module_path = $this->moduleExtensionList->getPath('oit');
    $location = Xss::filter($_SERVER['REQUEST_URI']);
    $host_alt = $this->t('Bob Barker as your 404 host');
    $carey = 0;
    if (date('m-d') == '04-23' || date('m-d') == '11-02' || $location == '/ohio') {
      $carey = 1;
      $host_alt = $this->t('Drew Carrey as your 404 host');
    }
    $location = substr($location, 1);
    $location = str_replace('/', ' ', $location);
    // Get users ip address.
    $ip = $ip = $this->requestStack->getClientIp();
    $host = $carey ? "404_carey.png" : "404_barker.png";
    $custom['string'] = [
      '#type' => 'inline_template',
      '#attached' => [
        'library' => [
          'oit/404',
        ],
      ],
      '#template' => '
        <div id="containAll404">
        <div id="buff404">
            <img src="{{ module_path }}/404_buff.jpg" alt="{{ buffimg_alt }}" />
            <h3>{{ title }}</h3>
          </div>
          <div class="flex">
            <div class="flex-one-third">
              <a class="icon button" href="/search/cse?keys={{ location }}"><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 34" class="svg-icon search" width="20">
                <use fill="#fff" xlink:href="/sites/default/files/svg/sprite_5.svg#search"></use>
                </svg></span>&nbsp;&nbsp; {{ button_search }}</a>
            </div>
            <div class="flex-one-third">
              <a class="icon button" href="/node/24951"><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 34" class="svg-icon megaphone" width="20">
                <use fill="#fff" xlink:href="/sites/default/files/svg/sprite_5.svg#megaphone"></use>
                </svg></span>&nbsp;&nbsp; {{ button_report }}</a>
            </div>
            <div class="flex-one-third">
              <div class="icon button" id="toggle404"><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 34" class="svg-icon circleoflife" width="20">
                <use fill="#fff" xlink:href="/sites/default/files/svg/sprite_5.svg#circleoflife"></use>
                </svg></span>&nbsp;&nbsp; {{ button_spin }}!</div>
            </div>
          </div>
          <div class="flex spin-contain" style="display:none;">
            <div class="flex-one-half">
              <div id="mainbox-container">
                <div id="mainbox" class="mainbox">
                  <div id="box" class="box">
                  </div>
                  <button class="spin">{{ spin }}</button>
                  <img src="{{ module_path }}/spin-wheel-outer.png" alt="{{ wheel_img }}" style="width:350px; position: absolute; top:0; z-index: -1;" />
                </div>
              </div>
            </div>
            <div class="flex-one-half">
              <div id="show-host">
                <img src="{{ module_path }}/{{ host }}" alt="{{ host_alt }}" />
              </div>
            </div>
            <div id="prize">{{ spint_text }}!</div>
          </div>
        </div>
        ',
      '#context' => [
        'title' => $this->t('Hmm...looks like something went wrong.'),
        'module_path' => "/$module_path/images/404",
        'host' => $host,
        'location' => $location,
        'buffimg_alt' => $this->t('Buffalo holding broken plug - text 404'),
        'wheel_img' => $this->t('Wheel'),
        'host_alt' => $host_alt,
        'button_search' => $this->t('Search OIT Site'),
        'button_report' => $this->t('Report an Issue'),
        'button_spin' => $this->t('Spin the Wheel'),
        'spin' => $this->t('Spin'),
        'spint_text' => $this->t('Spin the wheel to go to a random page in the service area the spinner lands on'),
      ],
    ];
    return [
      '#markup' => $this->renderer->render($custom),
    ];
  }

  /**
   * Routes for zap.
   */
  public function oitSamlLogin() {
    // Getting the referer.
    $request = \Drupal::request();
    $referer = $request->headers->get('referer');

    // Getting the base url.
    $base_url = Request::createFromGlobals()->getSchemeAndHttpHost();

    // Getting the alias or the relative path.
    $alias = Xss::filter(substr($referer, strlen($base_url)));

    // Set destination
    $destination = $alias == "" ? "/" : $alias;

    // Forward user to /saml_login?destination=$destination.
    $path = Url::fromRoute('simplesamlphp_auth.saml_login', [], ['query' => ['destination' => $destination]])->toString();
    $redirect = new RedirectResponse($path);
    $redirect->send();

    return [];
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
    $module_path = $this->moduleExtensionList->getPath('oit');
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
    $svg = $this->shortcodeSvgIcon;
    $title = $this->t('Request Portal');
    $search = '<form action="/search/cse" method="get" id="search-request" accept-charset="UTF-8" class="search" data-drupal-form-fields="edit-keys"><label for="edit-keys" class="visually-hidden form-item__label">Search</label> <input title="Enter the terms you wish to search for." placeholder="Get help with..." autocomplete="off" type="search" id="search-keys" name="keys" value="" size="15" maxlength="128" class="form-search form-item__textfield"><input data-drupal-selector="submit-search" type="submit" id="submit-search" value="Search" class="button"></form>';
    $issue_query = $this->blockUuidQuery;
    $issue_query->getBidByUuid('04d5cc3e-8b9d-4bb7-8cab-16f162cb729a');
    $issue = $issue_query->loadBlock();
    $request_query = $this->blockUuidQuery;
    $request_query->getBidByUuid('3a69be06-3b81-4a30-9996-0da3d6c45e8d');
    $request = $request_query->loadBlock();
    $cases_query = $this->blockUuidQuery;
    $cases_query->getBidByUuid('f4bc74b5-ba88-4b86-86e4-58d9ce96260b');
    $my_cases = $cases_query->loadBlock();

    $topreq_query = $this->blockUuidQuery;
    $topreq_query->getBidByUuid('9c8e8f57-f654-4976-a4bd-85acb21f0457');
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
      '#markup' => $this->renderer->render($page),
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
    $service_dashboard = $this->serviceHealth;
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
      '#markup' => $this->renderer->render($build),
      '#allowed_tags' => ['svg', 'circle'],
    ];
  }

}
