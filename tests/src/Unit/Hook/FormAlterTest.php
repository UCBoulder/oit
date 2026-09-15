<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\FormHooks;
use Drupal\oit\Plugin\Domain;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for formAlter() (spec section 5.2) and newsNodeFormSubmit() (5.3).
 *
 * Domain::getDomain() is mocked to a non-oit value in every case so the
 * procedural functions from oit.module (section 3.2) are never reached; the
 * oit-domain branches stay uncovered here, per spec section 5.1. The three
 * defect-regression rows (missing '#id', service alert with no body, webform
 * captcha roles NULL) live in FormAlterRegressionTest and are not repeated.
 */
#[Group('oit')]
#[CoversMethod(FormHooks::class, 'formAlter')]
#[CoversMethod(FormHooks::class, 'newsNodeFormSubmit')]
class FormAlterTest extends DrupalUnitTestCase {

  /**
   * {@inheritdoc}
   *
   * The beStrictAboutChangesToGlobalState setting (spec section 2.1) catches
   * a leaked container, so this must run unconditionally, including when a
   * newsNodeFormSubmit() test fails part-way through.
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Builds a FormHooks instance with mocked dependencies.
   *
   * @param array $options
   *   Optional overrides: 'roles', 'domain', 'configFactory', 'routeMatch',
   *   'requestStack', 'query' (used to build a requestStack when one is not
   *   supplied), 'messenger', 'killSwitch', 'loggerFactory'.
   *
   * @return \Drupal\oit\Hook\FormHooks
   *   The configured hooks object.
   */
  protected function buildHooks(array $options = []): FormHooks {
    $hooks = new FormHooks(
      $options['routeMatch'] ?? $this->createMock(RouteMatchInterface::class),
      $this->buildCurrentUser($options['roles'] ?? []),
      $options['configFactory'] ?? $this->buildConfigFactory(),
      $options['requestStack'] ?? $this->buildRequestStack($options['query'] ?? []),
      $options['messenger'] ?? $this->createMock(MessengerInterface::class),
      $options['killSwitch'] ?? $this->createMock(KillSwitch::class),
      $options['loggerFactory'] ?? $this->createMock(LoggerChannelFactoryInterface::class),
      $this->buildDomain($options['domain'] ?? 'na'),
      $this->buildResolver(),
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());

    return $hooks;
  }

  /**
   * Builds a FormHooks subclass that records sendRedirect() calls.
   *
   * The real sendRedirect() echoes a response body via send(), which the
   * strictness block in phpunit.xml.dist reports as test output. This
   * anonymous subclass overrides the seam from section 3.1 to record the
   * URL and status instead, per spec section 5.2 ("Login form, redirect").
   *
   * @param array $options
   *   Same overrides as buildHooks().
   *
   * @return \Drupal\oit\Hook\FormHooks
   *   A FormHooks instance with a public 'sentRedirects' property, each
   *   entry shaped as ['url' => string, 'status' => int].
   */
  protected function buildRecordingHooks(array $options = []): FormHooks {
    $hooks = new class(
      $options['routeMatch'] ?? $this->createMock(RouteMatchInterface::class),
      $this->buildCurrentUser($options['roles'] ?? []),
      $options['configFactory'] ?? $this->buildConfigFactory(),
      $options['requestStack'] ?? $this->buildRequestStack($options['query'] ?? []),
      $options['messenger'] ?? $this->createMock(MessengerInterface::class),
      $options['killSwitch'] ?? $this->createMock(KillSwitch::class),
      $options['loggerFactory'] ?? $this->createMock(LoggerChannelFactoryInterface::class),
      $this->buildDomain($options['domain'] ?? 'na'),
      $this->buildResolver(),
    ) extends FormHooks {

      /**
       * Recorded sendRedirect() calls.
       *
       * @var array
       */
      public array $sentRedirects = [];

      /**
       * {@inheritdoc}
       */
      protected function sendRedirect(string $url, int $status = 302): void {
        $this->sentRedirects[] = ['url' => $url, 'status' => $status];
      }

    };
    $hooks->setStringTranslation($this->getStringTranslationStub());

    return $hooks;
  }

  /**
   * Builds a mocked current user reporting the given roles.
   *
   * @param array $roles
   *   The roles to report.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The mocked current user.
   */
  protected function buildCurrentUser(array $roles): AccountProxyInterface {
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('getRoles')->willReturn($roles);

    return $current_user;
  }

  /**
   * Builds a mocked Domain service reporting the given domain.
   *
   * @param string $domain
   *   The domain identifier to report. Always non-'oit' in this suite; see
   *   spec section 3.2.
   *
   * @return \Drupal\oit\Plugin\Domain
   *   The mocked domain service.
   */
  protected function buildDomain(string $domain): Domain {
    $domain_mock = $this->createMock(Domain::class);
    $domain_mock->method('getDomain')->willReturn($domain);

    return $domain_mock;
  }

  /**
   * Builds a mocked extension path resolver pointing at the real module.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver
   *   The mocked resolver.
   */
  protected function buildResolver(): ExtensionPathResolver {
    $resolver = $this->createMock(ExtensionPathResolver::class);
    $resolver->method('getPath')->willReturn(dirname(__DIR__, 4));

    return $resolver;
  }

  /**
   * Builds a config factory returning canned values per config name.
   *
   * @param array $values
   *   Keyed by config name, each an array of key => value pairs returned
   *   from that config's get().
   * @param array $is_new
   *   Keyed by config name, the isNew() value to report. Defaults to FALSE.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The mocked config factory.
   */
  protected function buildConfigFactory(array $values = [], array $is_new = []): ConfigFactoryInterface {
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturnCallback(function (string $name) use ($values, $is_new) {
      $config_values = $values[$name] ?? [];
      $config = $this->createMock(ImmutableConfig::class);
      $config->method('get')->willReturnCallback(fn (string $key) => $config_values[$key] ?? NULL);
      $config->method('isNew')->willReturn($is_new[$name] ?? FALSE);

      return $config;
    });

    return $factory;
  }

  /**
   * Builds a request stack carrying a single real request with a query.
   *
   * A real Request built with a query array satisfies both the
   * query->get('name') and query->all() shapes the branches under test use.
   *
   * @param array $query
   *   The query string parameters.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack.
   */
  protected function buildRequestStack(array $query = []): RequestStack {
    $stack = new RequestStack();
    $stack->push(new Request($query));

    return $stack;
  }

  /**
   * Runs formAlter() on a fresh hooks object and returns the altered form.
   *
   * @param string $form_id
   *   The form id.
   * @param array $form
   *   The starting form array.
   * @param array $options
   *   Overrides passed through to buildHooks().
   *
   * @return array
   *   The altered form array.
   */
  protected function runFormAlter(string $form_id, array $form = [], array $options = []): array {
    $hooks = $this->buildHooks($options);
    $form_state = $this->createMock(FormStateInterface::class);
    $hooks->formAlter($form, $form_state, $form_id);

    return $form;
  }

  /**
   * Builds a route match reporting the given 'node' route parameter.
   *
   * @param mixed $node
   *   The value to report for the 'node' parameter.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The mocked route match.
   */
  protected function buildRouteMatchWithNode(mixed $node): RouteMatchInterface {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('node')->willReturn($node);

    return $route_match;
  }

  /**
   * Builds a fake node-like object reporting a fixed id.
   *
   * @param int $id
   *   The id to report.
   *
   * @return object
   *   An object with an id() method.
   */
  protected function fakeNode(int $id): object {
    return new class($id) {

      /**
       * Constructs the fake node.
       *
       * @param int $id
       *   The id to report.
       */
      public function __construct(protected int $id) {}

      /**
       * Returns the fixed id.
       */
      public function id() {
        return $this->id;
      }

    };
  }

  /* --------------------------------------------------------------------
   * Webform captcha add/remove link.
   * ------------------------------------------------------------------ */

  /**
   * Tests no '#prefix' is set when the route carries no node.
   */
  public function testWebformCaptchaNoNode(): void {
    $form = $this->runFormAlter('irrelevant_form_id', ['#webform_id' => 'wf1'], [
      'roles' => ['administrator'],
      'routeMatch' => $this->buildRouteMatchWithNode(NULL),
    ]);

    $this->assertArrayNotHasKey('#prefix', $form);
  }

  /**
   * Tests no '#prefix' is set when the route node is a string.
   */
  public function testWebformCaptchaStringNode(): void {
    $form = $this->runFormAlter('irrelevant_form_id', ['#webform_id' => 'wf1'], [
      'roles' => ['administrator'],
      'routeMatch' => $this->buildRouteMatchWithNode('some-string'),
    ]);

    $this->assertArrayNotHasKey('#prefix', $form);
  }

  /**
   * Tests no '#prefix' is set and config is never read for a non-admin.
   */
  public function testWebformCaptchaNonAdmin(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->never())->method('get');

    $form = $this->runFormAlter('irrelevant_form_id', ['#webform_id' => 'wf1'], [
      'roles' => [],
      'routeMatch' => $this->buildRouteMatchWithNode($this->fakeNode(5)),
      'configFactory' => $config_factory,
    ]);

    $this->assertArrayNotHasKey('#prefix', $form);
  }

  /**
   * Tests the add-captcha link is shown when no captcha point exists yet.
   */
  public function testWebformCaptchaAddLink(): void {
    $config_factory = $this->buildConfigFactory(
      ['webform.webform.wf1' => ['access.create.roles' => ['anonymous']]],
      ['captcha.captcha_point.webform_add_5' => TRUE],
    );

    $form = $this->runFormAlter('irrelevant_form_id', ['#webform_id' => 'wf1'], [
      'roles' => ['administrator'],
      'routeMatch' => $this->buildRouteMatchWithNode($this->fakeNode(5)),
      'configFactory' => $config_factory,
    ]);

    $this->assertStringContainsString('captcha-points/add?destination=node/5&amp;webform_id=5', $form['#prefix']);
    $this->assertStringContainsString('Add captcha to form', $form['#prefix']);
  }

  /**
   * Tests the remove-captcha link is shown when a captcha point exists.
   */
  public function testWebformCaptchaRemoveLink(): void {
    $config_factory = $this->buildConfigFactory(
      ['webform.webform.wf1' => ['access.create.roles' => ['anonymous']]],
      ['captcha.captcha_point.webform_add_5' => FALSE],
    );

    $form = $this->runFormAlter('irrelevant_form_id', ['#webform_id' => 'wf1'], [
      'roles' => ['administrator'],
      'routeMatch' => $this->buildRouteMatchWithNode($this->fakeNode(5)),
      'configFactory' => $config_factory,
    ]);

    $this->assertStringContainsString('captcha-points/webform_add_5/delete?destination=node/5', $form['#prefix']);
    $this->assertStringContainsString('Remove captcha from form', $form['#prefix']);
  }

  /**
   * Tests no '#prefix' is set when the webform has no anonymous role.
   */
  public function testWebformCaptchaNoAnonymousRole(): void {
    $config_factory = $this->buildConfigFactory(
      ['webform.webform.wf1' => ['access.create.roles' => ['authenticated']]],
    );

    $form = $this->runFormAlter('irrelevant_form_id', ['#webform_id' => 'wf1'], [
      'roles' => ['administrator'],
      'routeMatch' => $this->buildRouteMatchWithNode($this->fakeNode(5)),
      'configFactory' => $config_factory,
    ]);

    $this->assertArrayNotHasKey('#prefix', $form);
  }

  /* --------------------------------------------------------------------
   * captcha_point_add_form defaults.
   * ------------------------------------------------------------------ */

  /**
   * Tests the label and formId defaults are set from the query string.
   */
  public function testCaptchaPointAddFormDefaults(): void {
    $form = $this->runFormAlter('captcha_point_add_form', [], ['query' => ['webform_id' => '7']]);

    $this->assertSame('webform_7', $form['label']['#default_value']);
    $this->assertSame('webform_add_7', $form['formId']['#default_value']);
  }

  /**
   * Tests the form is left untouched and formAlter returns early.
   */
  public function testCaptchaPointAddFormInvalidId(): void {
    $form = $this->runFormAlter('captcha_point_add_form', [], ['query' => ['webform_id' => 'abc']]);

    $this->assertSame([], $form);
  }

  /* --------------------------------------------------------------------
   * Dashboard category add/delete submit handlers.
   * ------------------------------------------------------------------ */

  /**
   * Tests the add submit callback is appended to both submit buttons.
   */
  public function testDashboardCategoryAddSubmit(): void {
    $form = $this->runFormAlter('taxonomy_term_service_dashboard_category_form');

    $this->assertContains('oit_servicealert_dashboard_category_add', $form['actions']['submit']['#submit']);
    $this->assertContains('oit_servicealert_dashboard_category_add', $form['actions']['overview']['#submit']);
  }

  /**
   * Tests the delete submit callback is appended to the submit button.
   */
  public function testDashboardCategoryDeleteSubmit(): void {
    $form = $this->runFormAlter('taxonomy_term_service_dashboard_category_delete_form');

    $this->assertContains('oit_servicealert_dashboard_category_delete', $form['actions']['submit']['#submit']);
  }

  /* --------------------------------------------------------------------
   * Loading message / GP body validate.
   * ------------------------------------------------------------------ */

  /**
   * Tests the loading suffix and library are added for news/alert forms.
   */
  #[DataProvider('loadingMessageFormIdProvider')]
  public function testLoadingMessageAttached(string $form_id): void {
    $form = $this->runFormAlter($form_id);

    $this->assertStringContainsString('loading-message', $form['actions']['submit']['#suffix']);
    $this->assertContains('oit/loading', $form['#attached']['library']);
  }

  /**
   * Data provider of form ids that get the loading message.
   *
   * @return array
   *   Test cases.
   */
  public static function loadingMessageFormIdProvider(): array {
    return [
      'node_news_form' => ['node_news_form'],
      'node_news_edit_form' => ['node_news_edit_form'],
      'node_service_alert_form' => ['node_service_alert_form'],
      'node_service_alert_edit_form' => ['node_service_alert_edit_form'],
    ];
  }

  /**
   * Tests the GP check validator is appended on the three edit forms.
   */
  #[DataProvider('gpBodyValidateFormIdProvider')]
  public function testGpBodyValidateAppended(string $form_id): void {
    $form = $this->runFormAlter($form_id);

    $this->assertContains('oit_news_gp_check', $form['#validate']);
  }

  /**
   * Data provider of form ids that get the GP body validator.
   *
   * @return array
   *   Test cases.
   */
  public static function gpBodyValidateFormIdProvider(): array {
    return [
      'node_news_edit_form' => ['node_news_edit_form'],
      'node_tutorial_edit_form' => ['node_tutorial_edit_form'],
      'node_page_edit_form' => ['node_page_edit_form'],
    ];
  }

  /* --------------------------------------------------------------------
   * Space monkey.
   * ------------------------------------------------------------------ */

  /**
   * Tests the spacemonkey library is attached for a matching search key.
   */
  public function testSpaceMonkeyAttachesLibrary(): void {
    $form = $this->runFormAlter('search_form', [], ['query' => ['keys' => 'space monkey']]);

    $this->assertContains('oit/spacemonkey', $form['#attached']['library']);
  }

  /**
   * Tests the spacemonkey library is not attached for an unrelated key.
   */
  public function testSpaceMonkeyNegative(): void {
    $form = $this->runFormAlter('search_form', [], ['query' => ['keys' => 'cats']]);

    $this->assertArrayNotHasKey('library', $form['#attached'] ?? []);
  }

  /**
   * Tests the spacemonkey library is not attached with no search keys.
   */
  public function testSpaceMonkeyNoKeys(): void {
    $form = $this->runFormAlter('search_form', [], ['query' => []]);

    $this->assertArrayNotHasKey('library', $form['#attached'] ?? []);
  }

  /* --------------------------------------------------------------------
   * Service alert form.
   * ------------------------------------------------------------------ */

  /**
   * Tests the service alert extras group, comment access and body fill.
   */
  public function testServiceAlertStructural(): void {
    $form = $this->runFormAlter('node_service_alert_form');

    $this->assertSame('details', $form['oit_sa_extras']['#type']);
    $this->assertSame('advanced', $form['oit_sa_extras']['#group']);
    $this->assertSame(100, $form['oit_sa_extras']['#weight']);
    $this->assertFalse($form['comment_node_service_alert']['#access']);
    $body = $form['body']['widget'][0]['#default_value'];
    $this->assertSame(7, substr_count($body, '<h2>'));
    $this->assertStringContainsString('<h2>Impact</h2>', $body);
  }

  /**
   * Tests the service alert field-to-group assignments.
   */
  #[DataProvider('serviceAlertFieldGroupProvider')]
  public function testServiceAlertFieldGroup(string $field, string $group): void {
    $form = $this->runFormAlter('node_service_alert_form');

    $this->assertSame($group, $form[$field]['#group']);
  }

  /**
   * Data provider for service alert field-to-group assignments.
   *
   * @return array
   *   Test cases.
   */
  public static function serviceAlertFieldGroupProvider(): array {
    return [
      'field_access_control_2' => ['field_access_control_2', 'oit_sa_extras'],
      'field_sympa_send' => ['field_sympa_send', 'options'],
    ];
  }

  /**
   * Tests an existing body default value is not overwritten.
   */
  public function testServiceAlertBodyPresentNotOverwritten(): void {
    $form = ['body' => ['widget' => [0 => ['#default_value' => 'Existing content']]]];
    $form = $this->runFormAlter('node_service_alert_form', $form);

    $this->assertSame('Existing content', $form['body']['widget'][0]['#default_value']);
  }

  /* --------------------------------------------------------------------
   * Sympa library, service alert and news forms.
   * ------------------------------------------------------------------ */

  /**
   * Tests 'oit/gin_sa' is attached when the sympa prod email is set.
   */
  #[DataProvider('sympaFormIdProvider')]
  public function testSympaLibraryAttached(string $form_id): void {
    $config_factory = $this->buildConfigFactory(['sympa.settings' => ['sympa_email_prod' => 'oit@colorado.edu']]);
    $form = $this->runFormAlter($form_id, [], ['configFactory' => $config_factory]);

    $this->assertContains('oit/gin_sa', $form['#attached']['library']);
  }

  /**
   * Tests 'oit/gin_sa' is not attached when the sympa prod email is empty.
   */
  #[DataProvider('sympaFormIdProvider')]
  public function testSympaLibraryNotAttached(string $form_id): void {
    $config_factory = $this->buildConfigFactory(['sympa.settings' => ['sympa_email_prod' => '']]);
    $form = $this->runFormAlter($form_id, [], ['configFactory' => $config_factory]);

    $this->assertNotContains('oit/gin_sa', $form['#attached']['library'] ?? []);
  }

  /**
   * Data provider of form ids reached by the sympa library check.
   *
   * @return array
   *   Test cases.
   */
  public static function sympaFormIdProvider(): array {
    return [
      'service alert form' => ['node_service_alert_form'],
      'news form' => ['node_news_form'],
    ];
  }

  /* --------------------------------------------------------------------
   * Search block autocomplete.
   * ------------------------------------------------------------------ */

  /**
   * Tests the autocomplete attribute is disabled with no oit placeholder.
   */
  public function testSearchBlockAutocompleteOff(): void {
    $form = $this->runFormAlter('search_block_form', ['keys' => []], ['domain' => 'na']);

    $this->assertSame('off', $form['keys']['#attributes']['autocomplete']);
    $this->assertArrayNotHasKey('placeholder', $form['keys']['#attributes']);
  }

  /* --------------------------------------------------------------------
   * Page form.
   * ------------------------------------------------------------------ */

  /**
   * Tests the page form's three details groups, template and libraries.
   */
  public function testPageFormStructural(): void {
    $form = $this->runFormAlter('node_page_form');

    $this->assertSame('details', $form['page_extras']['#type']);
    $this->assertSame(100, $form['page_extras']['#weight']);
    $this->assertSame(1, $form['page_extras']['#open']);

    $this->assertSame('details', $form['protected_downloads']['#type']);
    $this->assertSame(101, $form['protected_downloads']['#weight']);
    $this->assertSame(0, $form['protected_downloads']['#open']);

    $this->assertSame('details', $form['type_service']['#type']);
    $this->assertSame(102, $form['type_service']['#weight']);
    $this->assertSame(0, $form['type_service']['#open']);

    $this->assertSame('inline_template', $form['oit_advanced']['#type']);
    $this->assertSame(100, $form['oit_advanced']['#weight']);
    $this->assertArrayHasKey('#context', $form['oit_advanced']);

    $this->assertContains('oit/oit_clipboard', $form['#attached']['library']);
    $this->assertContains('webform/webform.element.select2', $form['#attached']['library']);
    $this->assertContains('oit/oit_node_page_form', $form['#attached']['library']);
  }

  /**
   * Tests the page form's field-to-group assignments.
   */
  #[DataProvider('pageFormFieldGroupProvider')]
  public function testPageFormFieldGroup(string $field, string $group): void {
    $form = $this->runFormAlter('node_page_form');

    $this->assertSame($group, $form[$field]['#group']);
  }

  /**
   * Data provider for page form field-to-group assignments.
   *
   * @return array
   *   Test cases.
   */
  public static function pageFormFieldGroupProvider(): array {
    return [
      'field_oit_category' => ['field_oit_category', 'page_extras'],
      'field_access_control_2' => ['field_access_control_2', 'page_extras'],
      'field_show_child_links' => ['field_show_child_links', 'page_extras'],
      'upload' => ['upload', 'page_extras'],
      'field_dl_facstaff' => ['field_dl_facstaff', 'protected_downloads'],
      'field_dl_student' => ['field_dl_student', 'protected_downloads'],
      'field_dl_authenticated' => ['field_dl_authenticated', 'protected_downloads'],
      'taxonomy_vocabulary_11' => ['taxonomy_vocabulary_11', 'type_service'],
      'field_service_main_page' => ['field_service_main_page', 'type_service'],
      'field_services_related' => ['field_services_related', 'type_service'],
      'field_tut_comp_type_d7' => ['field_tut_comp_type_d7', 'type_service'],
      'field_software_download_link' => ['field_software_download_link', 'type_service'],
    ];
  }

  /**
   * Tests neither sub-type block runs when no 'type' query param is present.
   */
  public function testPageFormNoTypeQuery(): void {
    $form = $this->runFormAlter('node_page_form');

    $this->assertArrayNotHasKey('field_faq', $form);
    $this->assertArrayNotHasKey('field_faq_section_title', $form);
    $this->assertSame(0, $form['type_service']['#open']);
    $this->assertArrayNotHasKey('body', $form);
  }

  /**
   * Tests the 'service' page sub-type block.
   */
  public function testPageFormTypeService(): void {
    $form = $this->runFormAlter('node_page_form', [], ['query' => ['type' => 'service']]);

    $this->assertFalse($form['field_faq']['#access']);
    $this->assertFalse($form['field_faq_section_title']['#access']);
    $this->assertSame(1, $form['type_service']['#open']);
    $this->assertStringContainsString('<h2>Features</h2>', $form['body']['widget'][0]['#default_value']);
    $this->assertSame([1039], $form['field_oit_category']['widget']['#default_value']);
    $this->assertTrue($form['taxonomy_vocabulary_11']['widget']['#required']);
    $this->assertSame('Create Service Page', (string) $form['#title']);
  }

  /**
   * Tests the 'accessibility' page sub-type block.
   */
  public function testPageFormTypeAccessibility(): void {
    $form = $this->runFormAlter('node_page_form', [], ['query' => ['type' => 'accessibility']]);

    $this->assertSame([847], $form['field_oit_category']['widget']['#default_value']);
    $this->assertFalse($form['type_service']['#access']);
  }

  /* --------------------------------------------------------------------
   * News form.
   * ------------------------------------------------------------------ */

  /**
   * Tests the news form's extras group, libraries, validator and submit.
   */
  public function testNewsFormStructural(): void {
    $form = $this->runFormAlter('node_news_form');

    $this->assertSame('details', $form['oit_news_extras']['#type']);
    $this->assertSame('advanced', $form['oit_news_extras']['#group']);
    $this->assertSame(100, $form['oit_news_extras']['#weight']);

    $this->assertContains('webform/webform.element.select2', $form['#attached']['library']);
    $this->assertContains('oit/oit_node_news_form', $form['#attached']['library']);
    $this->assertContains('oit_news_types_categories', $form['#validate']);
    $this->assertContains([FormHooks::class, 'newsNodeFormSubmit'], $form['actions']['submit']['#submit']);
  }

  /**
   * Tests the news form's field-to-group assignments.
   */
  #[DataProvider('newsFormFieldGroupProvider')]
  public function testNewsFormFieldGroup(string $field, string $group): void {
    $form = $this->runFormAlter('node_news_form');

    $this->assertSame($group, $form[$field]['#group']);
  }

  /**
   * Data provider for news form field-to-group assignments.
   *
   * @return array
   *   Test cases.
   */
  public static function newsFormFieldGroupProvider(): array {
    return [
      'field_oit_category' => ['field_oit_category', 'oit_news_extras'],
      'field_access_control_2' => ['field_access_control_2', 'oit_news_extras'],
      'taxonomy_vocabulary_11' => ['taxonomy_vocabulary_11', 'oit_news_extras'],
      'field_oit_page_file_attatchment' => ['field_oit_page_file_attatchment', 'oit_news_extras'],
      'field_oit_news_front_image' => ['field_oit_news_front_image', 'oit_news_extras'],
      'field_oit_page_related_content' => ['field_oit_page_related_content', 'oit_news_extras'],
      'field_sympa_send' => ['field_sympa_send', 'options'],
    ];
  }

  /* --------------------------------------------------------------------
   * Webform node form.
   * ------------------------------------------------------------------ */

  /**
   * Tests the webform node extras group is created.
   */
  public function testWebformNodeStructural(): void {
    $form = $this->runFormAlter('node_webform_form');

    $this->assertSame('details', $form['oit_webform_extras']['#type']);
    $this->assertSame('advanced', $form['oit_webform_extras']['#group']);
    $this->assertSame(100, $form['oit_webform_extras']['#weight']);
  }

  /**
   * Tests the webform node form's field-to-group assignments.
   */
  #[DataProvider('webformNodeFieldGroupProvider')]
  public function testWebformNodeFieldGroup(string $field, string $group): void {
    $form = $this->runFormAlter('node_webform_form');

    $this->assertSame($group, $form[$field]['#group']);
  }

  /**
   * Data provider for webform node form field-to-group assignments.
   *
   * @return array
   *   Test cases.
   */
  public static function webformNodeFieldGroupProvider(): array {
    return [
      'field_access_control_2' => ['field_access_control_2', 'oit_webform_extras'],
      'field_oit_category' => ['field_oit_category', 'oit_webform_extras'],
    ];
  }

  /* --------------------------------------------------------------------
   * Tutorial node form.
   * ------------------------------------------------------------------ */

  /**
   * Tests the tutorial node extras group is created.
   */
  public function testTutorialNodeStructural(): void {
    $form = $this->runFormAlter('node_tutorial_form');

    $this->assertSame('details', $form['oit_page_extras']['#type']);
    $this->assertSame('advanced', $form['oit_page_extras']['#group']);
    $this->assertSame(100, $form['oit_page_extras']['#weight']);
  }

  /**
   * Tests the tutorial node form's field-to-group assignments.
   */
  #[DataProvider('tutorialNodeFieldGroupProvider')]
  public function testTutorialNodeFieldGroup(string $field, string $group): void {
    $form = $this->runFormAlter('node_tutorial_form');

    $this->assertSame($group, $form[$field]['#group']);
  }

  /**
   * Data provider for tutorial node form field-to-group assignments.
   *
   * @return array
   *   Test cases.
   */
  public static function tutorialNodeFieldGroupProvider(): array {
    return [
      'field_access_control_2' => ['field_access_control_2', 'oit_page_extras'],
      'field_oit_category' => ['field_oit_category', 'oit_page_extras'],
      'taxonomy_vocabulary_11' => ['taxonomy_vocabulary_11', 'oit_page_extras'],
      'field_tut_comp_type_d7' => ['field_tut_comp_type_d7', 'oit_page_extras'],
      'upload' => ['upload', 'oit_page_extras'],
    ];
  }

  /* --------------------------------------------------------------------
   * Login form.
   * ------------------------------------------------------------------ */

  /**
   * Tests the login form is shown as-is when show_login_form is TRUE.
   */
  public function testLoginFormShown(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('deleteAll');
    $kill_switch = $this->createMock(KillSwitch::class);
    $kill_switch->expects($this->never())->method('trigger');

    $config_factory = $this->buildConfigFactory(['oit.settings' => ['show_login_form' => TRUE]]);

    $hooks = $this->buildRecordingHooks([
      'configFactory' => $config_factory,
      'messenger' => $messenger,
      'killSwitch' => $kill_switch,
    ]);
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $hooks->formAlter($form, $form_state, 'user_login_form');

    $this->assertSame('inline_template', $form['login_words']['#type']);
    $this->assertSame([], $hooks->sentRedirects);
  }

  /**
   * Tests the login form redirects to SAML when show_login_form is FALSE.
   */
  public function testLoginFormRedirect(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('deleteAll');
    $kill_switch = $this->createMock(KillSwitch::class);
    $kill_switch->expects($this->once())->method('trigger');

    $logger_channel = $this->createMock(LoggerChannelInterface::class);
    $logger_channel->expects($this->once())
      ->method('notice')
      ->with($this->stringContains('@destination'), ['@destination' => '?destination=%2Fnode%2F5']);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('oit')->willReturn($logger_channel);

    $config_factory = $this->buildConfigFactory(['oit.settings' => ['show_login_form' => FALSE]]);

    $hooks = $this->buildRecordingHooks([
      'configFactory' => $config_factory,
      'messenger' => $messenger,
      'killSwitch' => $kill_switch,
      'loggerFactory' => $logger_factory,
      'query' => ['destination' => '/node/5'],
    ]);
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $hooks->formAlter($form, $form_state, 'user_login_form');

    $this->assertCount(1, $hooks->sentRedirects);
    $this->assertSame('/saml/login?destination=%2Fnode%2F5', $hooks->sentRedirects[0]['url']);
    $this->assertSame(302, $hooks->sentRedirects[0]['status']);

    $this->assertArrayNotHasKey('name', $form);
    $this->assertArrayNotHasKey('pass', $form);
    $this->assertArrayNotHasKey('actions', $form);

    $this->assertSame('', $form['login_words']['#context']['saml_url']);
    $this->assertContains('button', $form['samlauth_auth_login_link']['#attributes']['class']);
    $this->assertContains('ext', $form['samlauth_auth_login_link']['#attributes']['class']);
  }

  /* --------------------------------------------------------------------
   * newsNodeFormSubmit() (spec section 5.3).
   * ------------------------------------------------------------------ */

  /**
   * Tests the method returns before touching the container when not promoted.
   */
  public function testNewsNodeFormSubmitReturnsEarlyWhenNotPromoted(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with(['promote', 'value'])->willReturn(0);
    // No container is set. If the method reached \Drupal::entityTypeManager()
    // it would fatal here, proving the early return.
    $form = [];

    FormHooks::newsNodeFormSubmit($form, $form_state);

    $this->assertSame([], $form);
  }

  /**
   * Tests promoted news nodes cause other promoted news nodes to be demoted.
   */
  public function testNewsNodeFormSubmitDemotesOtherPromotedNews(): void {
    $node1_sets = [];
    $node1 = $this->createMock(NodeInterface::class);
    $node1->method('set')->willReturnCallback(function ($name, $value) use (&$node1_sets, &$node1): NodeInterface {
      $node1_sets[] = [$name, $value];
      return $node1;
    });
    $node1->expects($this->once())->method('save');

    $node2_sets = [];
    $node2 = $this->createMock(NodeInterface::class);
    $node2->method('set')->willReturnCallback(function ($name, $value) use (&$node2_sets, &$node2): NodeInterface {
      $node2_sets[] = [$name, $value];
      return $node2;
    });
    $node2->expects($this->once())->method('save');

    $conditions = [];
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnCallback(function (...$args) use (&$conditions, &$query): QueryInterface {
      $conditions[] = $args;
      return $query;
    });
    $query->expects($this->once())->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->expects($this->once())->method('sort')->with('created', 'DESC')->willReturnSelf();
    $query->expects($this->once())->method('execute')->willReturn([2, 3]);

    $node_storage = $this->createMock(EntityStorageInterface::class);
    $node_storage->method('getQuery')->willReturn($query);
    $node_storage->method('loadMultiple')->with([2, 3])->willReturn([$node1, $node2]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('node')->willReturn($node_storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $entity = $this->createMock(NodeInterface::class);
    $entity->method('id')->willReturn(1);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($entity);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with(['promote', 'value'])->willReturn(1);
    $form_state->method('getFormObject')->willReturn($form_object);

    $form = [];
    FormHooks::newsNodeFormSubmit($form, $form_state);

    // Assert the query conditions, not just the result: 'nid != 1' is what
    // stops the node demoting itself, and it is the part a refactor would
    // most plausibly drop.
    $this->assertContains(['type', 'news', NULL, NULL], $conditions);
    $this->assertContains(['promote', 1, NULL, NULL], $conditions);
    $this->assertContains(['nid', 1, '!=', NULL], $conditions);

    $this->assertSame([['promote', 0], ['field_sympa_send', 0]], $node1_sets);
    $this->assertSame([['promote', 0], ['field_sympa_send', 0]], $node2_sets);
  }

}
