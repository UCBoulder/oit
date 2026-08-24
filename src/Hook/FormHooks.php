<?php

namespace Drupal\oit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\oit\Plugin\Domain;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for form alterations.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new FormHooks object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $pageCacheKillSwitch
   *   The page cache kill switch.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\oit\Plugin\Domain $domain
   *   The OIT domain service.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected AccountProxyInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
    protected MessengerInterface $messenger,
    protected KillSwitch $pageCacheKillSwitch,
    protected LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'oit.domain')]
    protected Domain $domain,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {

    if (array_key_exists('#webform_id', $form)) {
      // Get node id current webform is on.
      $node = $this->routeMatch->getParameter('node');
      if ($node != NULL) {

        $node_id = is_string($node) ? 0 : $node->id();
        $current_user = $this->currentUser;
        $roles = $current_user->getRoles();

        if ($node_id && in_array('administrator', $roles)) {
          $webform_id = $form['#webform_id'];
          $config = $this->configFactory->get('webform.webform.' . $webform_id);
          $webform_roles = $config->get('access.create.roles');
          if (!is_array($roles)) {
            $roles = [];
          }

          $anonymous_set = FALSE;
          foreach ($webform_roles as $webform_role) {
            if ($webform_role == 'anonymous') {
              $anonymous_set = TRUE;
            }
          }

          if ($anonymous_set) {
            $captcha_config = $this->configFactory->get('captcha.captcha_point.webform_add_' . $node_id)->isNew();

            if ($captcha_config) {
              $button_text = $this->t('Add captcha to form');
              $form['#prefix'] = Xss::filter("<ul class='no-list-style'><li class='red'><a href='/admin/config/people/captcha/captcha-points/add?destination=node/$node_id&webform_id=$node_id' class='button icon'>⬇️ $button_text</a></li></ul>");
            }
            else {
              $button_text = $this->t('Remove captcha from form');
              $form['#prefix'] = Xss::filter("<ul class='no-list-style'><li class='red'><a href='/admin/config/people/captcha/captcha-points/webform_add_$node_id/delete?destination=node/$node_id' class='button icon'>🔥 $button_text</a></li></ul>");
            }

          }
        }
      }
    }

    // Show a deployment window warning on webform nodes and entities.
    $this->securityWindowWarning($form, $form_id);

    // admin/config/people/captcha/captcha-points/add
    // Set defaults if 'webform_id' is in query string.
    if ($form_id == 'captcha_point_add_form') {
      // Get 'webform_id' from query string.
      $request = $this->requestStack->getCurrentRequest();
      $defaults = $this->captchaPointDefaults($request->query->get('webform_id'));

      if ($defaults === NULL) {
        return;
      }

      $form['label']['#default_value'] = $defaults['label'];
      $form['formId']['#default_value'] = $defaults['formId'];
    }

    if ($form_id == "taxonomy_term_service_dashboard_category_form") {
      $form['actions']['submit']['#submit'][] = 'oit_servicealert_dashboard_category_add';
      $form['actions']['overview']['#submit'][] = 'oit_servicealert_dashboard_category_add';
    }

    if ($form_id == "taxonomy_term_service_dashboard_category_delete_form") {
      $form['actions']['submit']['#submit'][] = 'oit_servicealert_dashboard_category_delete';
    }

    if ($form_id == 'node_news_form' || $form_id == 'node_news_edit_form' ||
    $form_id == 'node_service_alert_form' || $form_id == 'node_service_alert_edit_form'
    ) {
      $form['actions']['submit']['#suffix'] = '<div class="loading-message">' . $this->t('The page is loading, do not submit. Reload if necessary.') . '</div>';
      $form['#attached']['library'][] = 'oit/loading';
    }

    // GP blank body for editing news, tutorial, or page.
    if ($form_id == 'node_news_edit_form' || $form_id == 'node_tutorial_edit_form' || $form_id == 'node_page_edit_form') {
      // Add validation function.
      $form['#validate'][] = 'oit_news_gp_check';
    }

    // Set to oit domain on oit site when viewing the content.
    $domain = $this->domain->getDomain();

    if ($domain == 'oit') {
      oit_set_domain_defaults($form, 'oit', 'oit_colorado_edu');
    }

    if ($form['#id'] == 'views-exposed-form-moderated-content-moderated-content') {
      if ($domain == 'oit') {
        _oit_form_set_domain('content/moderated', 'oit_colorado_edu');
      }
    }

    if ($form_id == "views_form_content_page_1") {
      if ($domain == 'oit') {
        // Get user roles.
        $roles = $this->currentUser->getRoles();
        if (in_array('oda_super_editors', $roles) || in_array('oda_editors', $roles)) {
          $message = $this->t('You are logged into the OIT site and cannot edit content. Please log into the Data site to edit content.');
          $this->messenger->addMessage($message, 'warning');
          $form['#access'] = FALSE;
        }
        // Get query string.
        $query = $this->requestStack->getCurrentRequest()->query->all();
        $domain_set = FALSE;
        foreach ($query as $key => $value) {
          if ($key == 'field_domain_access_target_id') {
            $domain_set = TRUE;
          }
        }
        if (!$domain_set) {
          // Redirect to query string
          // "field_domain_access_target_id=oit_colorado_edu".
          $response = new RedirectResponse('/admin/content?' . $this->moderatedContentRedirectQuery($query));
          // @todo (C1): Calling send() inside hook_form_alter() bypasses the Symfony
          // kernel response pipeline. The proper fix is to convert this to a
          // #submit handler or KernelEvents::RESPONSE subscriber. Until then,
          // return immediately after send() so no further form-build code runs.
          $response->send();
          return;
        }
      }
    }

    // Add spacemonkey to search.
    if ($form_id == 'search_form') {
      // Get query string.
      $query = $this->requestStack->getCurrentRequest()->query->all();
      if ($this->isSpaceMonkey($query['keys'] ?? NULL)) {
        $form['#attached']['library'][] = 'oit/spacemonkey';
      }
    }

    switch ($form_id) {
      case "node_service_alert_form":
      case "node_service_alert_edit_form":
        $config = $this->configFactory->get('sympa.settings');
        $prod_email = $config->get('sympa_email_prod');

        if ($prod_email) {
          $form['#attached']['library'][] = 'oit/gin_sa';
        }
        // Group for page sub-type extra fields.
        $form['oit_sa_extras'] = [
          '#title' => $this->t('Service Alert Extras'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 1,
          '#weight' => 100,
        ];
        $form['field_access_control_2']['#group'] = 'oit_sa_extras';
        $form['field_sympa_send']['#group'] = 'options';
        // Death to comments.
        $form['comment_node_service_alert']['#access'] = FALSE;
        // Fill in empty body with template.
        if ($form['body']['widget'][0]['#default_value'] == NULL) {
          $form['body']['widget'][0]['#default_value'] = '<h2>Impact</h2><p></p>
            <h2>Scope</h2><p></p>
            <h2>Affected Services</h2><p></p>
            <h2>Affected Buildings</h2><p></p>
            <h2>For More Information</h2><p></p>
            <h2>Additional Information from Vendor</h2><p></p>
            <h2>Additional Information from UIS</h2><p></p>';
        }
        break;

      case "search_block_form":
        // Set search placeholder on oit.
        $domain = $this->domain->getDomain();
        if ($domain == 'oit') {
          $form['keys']['#attributes']['placeholder'] = $this->t('Search OIT');
        }
        $form['keys']['#attributes']['autocomplete'] = 'off';
        break;

      case "node_page_form":
      case "node_page_edit_form":
        // Group for page sub-type extra fields.
        $form['page_extras'] = [
          '#title' => $this->t('Page Extras'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 1,
          '#weight' => 100,
        ];
        $form['field_oit_category']['#group'] = 'page_extras';
        $form['field_access_control_2']['#group'] = 'page_extras';
        $form['field_show_child_links']['#group'] = 'page_extras';
        $form['upload']['#group'] = 'page_extras';

        // Private files.
        $form['protected_downloads'] = [
          '#title' => $this->t('Protected Downloads'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 0,
          '#weight' => 101,
        ];
        $form['field_dl_facstaff']['#group'] = 'protected_downloads';
        $form['field_dl_student']['#group'] = 'protected_downloads';
        $form['field_dl_authenticated']['#group'] = 'protected_downloads';
        // Group for service type.
        $form['type_service'] = [
          '#title' => $this->t('Service'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 0,
          '#weight' => 102,
        ];
        $form['taxonomy_vocabulary_11']['#group'] = 'type_service';
        $form['field_service_main_page']['#group'] = 'type_service';
        $form['field_services_related']['#group'] = 'type_service';
        $form['field_tut_comp_type_d7']['#group'] = 'type_service';
        $form['field_software_download_link']['#group'] = 'type_service';
        $request = $this->requestStack->getCurrentRequest();
        $request_type = $request->query->get("type");
        if (isset($request_type)) {
          // Show/hide fields that apply/don't apply to the service type.
          if ($request_type == "service") {
            $form['field_faq']['#access'] = FALSE;
            $form['field_faq_section_title']['#access'] = FALSE;
            $form['type_service']['#open'] = 1;
            $form['body']['widget'][0]['#default_value'] = '<h2>Features</h2><p>Features here.</p><h2>Related Policies</h2><p>Policies here</p><h2>Benefits</h2><p>Benefits here</p><h2>Cost</h2><p>Cost here</p><h2>Who can get it</h2><p>Who can get it here</p><h2>How to get it</h2><p>how to get it here</p><h2>Related Projects</h2><p>related projects here</p>';
            $form['field_oit_category']['widget']['#default_value'][] = 1039;
            $form['taxonomy_vocabulary_11']['widget']['#required'] = TRUE;
            $form['#title'] = $this->t('Create Service Page');
          }
          if ($request_type == "accessibility") {
            $form['field_oit_category']['widget']['#default_value'][] = 847;
            $form['type_service']['#access'] = FALSE;
          }
        }
        $form['#attached']['library'][] = 'oit/oit_clipboard';
        $form['#attached']['library'][] = 'webform/webform.element.select2';
        $form['#attached']['library'][] = 'oit/oit_node_page_form';
        $form['oit_advanced'] = [
          '#type' => 'inline_template',
          '#template' => '<details><summary>{{ summary }}</summary>
        <ul><li><a href="{{ icons_url }}" class="edit-button use-ajax" data-dialog-type="dialog" data-dialog-renderer="off_canvas" data-dialog-options="{&quot;width&quot;:400}">{{ icons_label }}</a></li><li><a href="{{ custom_js_url }}" target="_blank">{{ custom_js_label }}</a></li></ul>
        <h3>{{ flexbox_title }}</h3>
        <p>{{ flexbox_button | raw }}</p>
        <h3>{{ details_title }}</h3>
        <p>{{ details_button | raw }}</p>
        <p>{{ details_no_deets_button | raw }}</p>
        <h3>{{ columns_title }}</h3>
        <p>{{ columns_button | raw }}</p>
        <h3>{{ shortcode_block_title }}</h3>
        <p>{{ shortcode_block_button | raw }}</p>
        </details>',
          '#context' => [
            'summary' => $this->t('OIT advanced html'),
            'icons_url' => '/admin/config/content/shortcode_svg/svg_list',
            'icons_label' => $this->t('Icons shortcode panel'),
            'custom_js_url' => '/admin/config/development/asset-injector/js',
            'custom_js_label' => $this->t('Custom js/css (admin only)'),
            'flexbox_title' => $this->t('Flexbox Codez'),
            'flexbox_button' => '<button type="button" class="copy-icon" data-clipboard="flexBox">Copy flexbox</button>',
            'details_title' => $this->t('Details'),
            'details_button' => '<button type="button" class="copy-icon" data-clipboard="details">Copy details element</button>',
            'details_no_deets_button' => '<button type="button" class="copy-icon" data-clipboard="details-no-deets">Copy details element with class to hide show/hide links</button>',
            'columns_title' => $this->t('Columns'),
            'columns_button' => '<button type="button" class="copy-icon" data-clipboard="text-cols--3">Copy Columns class</button>',
            'shortcode_block_title' => $this->t('Shortcode Block embed'),
            'shortcode_block_button' => '<button type="button" class="copy-icon" data-clipboard="shortcode-block">Copy block shortcode embed</button>',
          ],
          '#weight' => 100,
        ];
        break;

      case "node_news_form":
      case "node_news_edit_form":
        $config = $this->configFactory->get('sympa.settings');
        $prod_email = $config->get('sympa_email_prod');

        if ($prod_email) {
          $form['#attached']['library'][] = 'oit/gin_sa';
        }

        // Group for page sub-type extra fields.
        $form['oit_news_extras'] = [
          '#title' => $this->t('News Extras'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 1,
          '#weight' => 100,
        ];
        $form['field_oit_category']['#group'] = 'oit_news_extras';
        $form['field_access_control_2']['#group'] = 'oit_news_extras';
        $form['taxonomy_vocabulary_11']['#group'] = 'oit_news_extras';
        $form['field_sympa_send']['#group'] = 'options';
        $form['field_oit_page_file_attatchment']['#group'] = 'oit_news_extras';
        $form['field_oit_news_front_image']['#group'] = 'oit_news_extras';
        $form['field_oit_page_related_content']['#group'] = 'oit_news_extras';
        $form['#attached']['library'][] = 'webform/webform.element.select2';
        $form['#attached']['library'][] = 'oit/oit_node_news_form';
        $form['#validate'][] = 'oit_news_types_categories';
        // Add submit handler.
        $form['actions']['submit']['#submit'][] = [self::class, 'newsNodeFormSubmit'];
        break;

      case "user_login_form":
        // Remove any messages so they don't show up after login.
        $this->messenger->deleteAll();
        $config = $this->configFactory->get('oit.settings');
        $show_login_form = $config->get('show_login_form');
        $form['login_words']['#markup'] = '<div class="login_text"></div>';
        $form['login_words']['#weight'] = -10;
        if (!$show_login_form) {
          // Cache is breaking the redirect, so kill it.
          $this->pageCacheKillSwitch->trigger();
          $dest_get = $this->requestStack->getCurrentRequest()->query->get('dest') ?? '';
          $destination_get = $this->requestStack->getCurrentRequest()->query->get('destination') ?? '';
          $destination = $this->buildLoginDestination($dest_get, $destination_get);
          // Drupal 10 add log message with $destination.
          $this->loggerFactory->get('oit')->notice('User login form redirecting to saml_login with destination: @destination', ['@destination' => $destination]);
          $response = new RedirectResponse('/saml/login' . $destination, 302);
          $response->send();
          unset($form['name']);
          unset($form['pass']);
          unset($form['actions']);
          $login_text = $this->t('Click below to login');
          $form['login_words']['#markup'] = "<div class='login_text'><p>$login_text</p></div>";
          $form['samlauth_auth_login_link']['#attributes']['class'][] = 'button';
          $form['samlauth_auth_login_link']['#attributes']['class'][] = 'ext';
          $form['#attached']['library'][] = 'oit/gsap';
          return;
        }
        break;

      // Imported from zap_initialize.
      case 'node_webform_form':
      case 'node_webform_edit_form':
        $form['oit_webform_extras'] = [
          '#title' => $this->t('Webform Extras'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 1,
          '#weight' => 100,
        ];
        $form['field_access_control_2']['#group'] = 'oit_webform_extras';
        $form['field_oit_category']['#group'] = 'oit_webform_extras';
        break;

      case 'node_tutorial_form':
      case 'node_tutorial_edit_form':
        $form['oit_page_extras'] = [
          '#title' => $this->t('Tutorial Extras'),
          '#type' => 'details',
          '#group' => 'advanced',
          '#open' => 1,
          '#weight' => 100,
        ];
        $form['field_access_control_2']['#group'] = 'oit_page_extras';
        $form['field_oit_category']['#group'] = 'oit_page_extras';
        $form['taxonomy_vocabulary_11']['#group'] = 'oit_page_extras';
        $form['field_tut_comp_type_d7']['#group'] = 'oit_page_extras';
        $form['upload']['#group'] = 'oit_page_extras';
        break;
    }
  }

  /**
   * Builds the default values for the captcha point add form.
   *
   * @param mixed $webform_id
   *   The raw 'webform_id' value from the query string.
   *
   * @return array|null
   *   An array with 'label' and 'formId' default values, or NULL when the
   *   webform id is missing or not a positive integer.
   */
  protected function captchaPointDefaults(mixed $webform_id): ?array {
    $webform_id = is_numeric($webform_id) ? intval($webform_id) : 0;

    if ($webform_id === 0) {
      return NULL;
    }

    return [
      'label' => 'webform_' . $webform_id,
      'formId' => 'webform_add_' . $webform_id,
    ];
  }

  /**
   * Determines whether a search key triggers the space monkey easter egg.
   *
   * @param mixed $keys
   *   The raw 'keys' value from the search query string.
   *
   * @return bool
   *   TRUE when the sanitized, lower-cased key matches a space monkey spelling.
   */
  protected function isSpaceMonkey(mixed $keys): bool {
    $search_key = isset($keys) ? Xss::filter(strtolower((string) $keys)) : '';

    return in_array($search_key, ['space monkey', 'space+monkey', 'spacemonkey'], TRUE);
  }

  /**
   * Builds the login redirect destination query fragment.
   *
   * The 'destination' value takes precedence over 'dest' when both are present
   * and internal. External URLs are rejected to prevent open redirects.
   *
   * @param string $dest
   *   The raw 'dest' query value.
   * @param string $destination
   *   The raw 'destination' query value.
   *
   * @return string
   *   A '?destination=...' fragment, or an empty string when neither value is
   *   a usable internal path.
   */
  protected function buildLoginDestination(string $dest, string $destination): string {
    $result = '';

    if (!empty($dest) && !UrlHelper::isExternal($dest)) {
      $result = '?destination=' . urlencode(Xss::filter($dest));
    }
    if (!empty($destination) && !UrlHelper::isExternal($destination)) {
      $result = '?destination=' . urlencode(Xss::filter($destination));
    }

    return $result;
  }

  /**
   * Builds the moderated content redirect query string.
   *
   * Injects the default domain access target id when it is not already present.
   *
   * @param array $query
   *   The current request query parameters.
   *
   * @return string
   *   The encoded query string including the default domain access target id.
   */
  protected function moderatedContentRedirectQuery(array $query): string {
    $query['field_domain_access_target_id'] = 'oit_colorado_edu';

    return http_build_query($query);
  }

  /**
   * Shows a warning on webform nodes and entities during the security window.
   *
   * Displayed Wednesday 1600–2200 UTC to administrator and pseudo_admin roles.
   *
   * @param array $form
   *   The form array.
   * @param string $form_id
   *   The form ID.
   * @param \DateTimeInterface|null $now
   *   The reference time to evaluate the window against. Defaults to the
   *   current UTC time. Primarily injectable to make the window testable.
   */
  protected function securityWindowWarning(array &$form, string $form_id, ?\DateTimeInterface $now = NULL): void {
    // Only act on webform submission forms (nodes) or webform entity forms.
    $is_webform_node = array_key_exists('#webform_id', $form);
    $is_webform_entity = str_starts_with($form_id, 'webform_') && str_ends_with($form_id, '_form');
    if (!$is_webform_node && !$is_webform_entity) {
      return;
    }

    // Only show to administrator and pseudo_admin roles.
    $roles = $this->currentUser->getRoles();
    if (!in_array('administrator', $roles) && !in_array('pseudo_admin', $roles)) {
      return;
    }

    // Check if it's Wednesday between 1600 and 2200 UTC.
    $now = $now ?? new \DateTime('now', new \DateTimeZone('UTC'));
    // ISO: 3 = Wednesday.
    $day_of_week = (int) $now->format('N');
    // 0–23.
    $hour = (int) $now->format('G');
    if ($day_of_week !== 3 || $hour < 16 || $hour >= 22) {
      return;
    }

    $emoji = '🚨🚨🚨🚨';
    $message = $this->t("@emoji This is the deployment window. The sites config will be backed up around 11am and any changes will be subject to loss if there's a deployment today. @emoji", ['@emoji' => $emoji]);

    $this->messenger->addMessage($message, 'warning');
  }

  /**
   * Submit handler for the news node form.
   *
   * Un-promotes other news nodes when a news node is promoted.
   *
   * Static so the callback stored in the cached form array does not
   * serialize this hook class and its injected services.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function newsNodeFormSubmit(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue(['promote', 'value'])) {
      return;
    }

    $node_id = $form_state->getFormObject()->getEntity()->id();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $nids = $node_storage->getQuery()
      ->condition('type', 'news')
      ->condition('promote', 1)
      ->condition('nid', $node_id, '!=')
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->execute();

    foreach ($node_storage->loadMultiple($nids) as $node) {
      $node->set('promote', 0);
      $node->set('field_sympa_send', 0);
      $node->save();
    }
  }

}
