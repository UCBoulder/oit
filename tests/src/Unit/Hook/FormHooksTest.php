<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\oit\Hook\FormHooks;
use Drupal\oit\Plugin\Domain;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the pure helpers in FormHooks.
 */
#[Group('oit')]
#[CoversClass(FormHooks::class)]
#[CoversMethod(FormHooks::class, 'captchaPointDefaults')]
#[CoversMethod(FormHooks::class, 'isSpaceMonkey')]
#[CoversMethod(FormHooks::class, 'buildLoginDestination')]
#[CoversMethod(FormHooks::class, 'moderatedContentRedirectQuery')]
#[CoversMethod(FormHooks::class, 'securityWindowWarning')]
#[CoversMethod(FormHooks::class, 'loginWords')]
#[CoversMethod(FormHooks::class, 'samlLoginUrl')]
class FormHooksTest extends DrupalUnitTestCase {

  /**
   * The hooks object under test.
   *
   * @var \Drupal\oit\Hook\FormHooks
   */
  protected FormHooks $hooks;

  /**
   * Invokes a protected method on the hooks object.
   *
   * @param string $method
   *   The protected method name.
   * @param array $args
   *   The arguments to pass.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invoke(string $method, array $args): mixed {
    $ref = new \ReflectionMethod($this->hooks, $method);
    $ref->setAccessible(TRUE);
    return $ref->invokeArgs($this->hooks, $args);
  }

  /**
   * Builds a FormHooks instance with mocked dependencies.
   *
   * @param array $roles
   *   The roles the mocked current user should report.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   An optional messenger mock; one is created when omitted.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $request_stack
   *   An optional request stack; a mock with no request is used when omitted.
   *
   * @return \Drupal\oit\Hook\FormHooks
   *   The configured hooks object.
   */
  protected function buildHooks(array $roles = [], ?MessengerInterface $messenger = NULL, ?RequestStack $request_stack = NULL): FormHooks {
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('getRoles')->willReturn($roles);

    $hooks = new FormHooks(
      $this->createMock(RouteMatchInterface::class),
      $current_user,
      $this->createMock(ConfigFactoryInterface::class),
      $request_stack ?? $this->createMock(RequestStack::class),
      $messenger ?? $this->createMock(MessengerInterface::class),
      $this->createMock(KillSwitch::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(Domain::class),
      $this->mockExtensionPathResolver(),
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());

    return $hooks;
  }

  /**
   * Builds an extension path resolver that points at the real module directory.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver
   *   The mocked resolver.
   */
  protected function mockExtensionPathResolver(): ExtensionPathResolver {
    $resolver = $this->createMock(ExtensionPathResolver::class);
    $resolver->method('getPath')->willReturn(dirname(__DIR__, 4));

    return $resolver;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->hooks = $this->buildHooks();
  }

  /**
   * Tests captchaPointDefaults builds defaults for valid ids.
   */
  #[DataProvider('captchaValidProvider')]
  public function testCaptchaPointDefaultsValid(mixed $input, array $expected): void {
    $this->assertSame($expected, $this->invoke('captchaPointDefaults', [$input]));
  }

  /**
   * Data provider for valid captcha ids.
   *
   * @return array
   *   Test cases.
   */
  public static function captchaValidProvider(): array {
    return [
      'numeric string' => ['5', ['label' => 'webform_5', 'formId' => 'webform_add_5']],
      'integer' => [12, ['label' => 'webform_12', 'formId' => 'webform_add_12']],
      'float-ish string truncates' => ['7.9', ['label' => 'webform_7', 'formId' => 'webform_add_7']],
    ];
  }

  /**
   * Tests captchaPointDefaults returns NULL for missing or invalid ids.
   */
  #[DataProvider('captchaInvalidProvider')]
  public function testCaptchaPointDefaultsInvalid(mixed $input): void {
    $this->assertNull($this->invoke('captchaPointDefaults', [$input]));
  }

  /**
   * Data provider for invalid captcha ids.
   *
   * @return array
   *   Test cases.
   */
  public static function captchaInvalidProvider(): array {
    return [
      'zero string' => ['0'],
      'zero int' => [0],
      'non-numeric' => ['abc'],
      'null' => [NULL],
    ];
  }

  /**
   * Tests the space monkey detection for matching and non-matching keys.
   */
  #[DataProvider('spaceMonkeyProvider')]
  public function testIsSpaceMonkey(mixed $input, bool $expected): void {
    $this->assertSame($expected, $this->invoke('isSpaceMonkey', [$input]));
  }

  /**
   * Data provider for space monkey keys.
   *
   * @return array
   *   Test cases.
   */
  public static function spaceMonkeyProvider(): array {
    return [
      'space monkey' => ['space monkey', TRUE],
      'plus variant' => ['space+monkey', TRUE],
      'no space' => ['spacemonkey', TRUE],
      'uppercase' => ['SPACE MONKEY', TRUE],
      'unrelated' => ['cat', FALSE],
      'empty' => ['', FALSE],
      'null' => [NULL, FALSE],
    ];
  }

  /**
   * Tests login destination building, including external URL rejection.
   */
  #[DataProvider('loginDestinationProvider')]
  public function testBuildLoginDestination(string $dest, string $destination, string $expected): void {
    $this->assertSame($expected, $this->invoke('buildLoginDestination', [$dest, $destination]));
  }

  /**
   * Data provider for login destination building.
   *
   * @return array
   *   Test cases.
   */
  public static function loginDestinationProvider(): array {
    return [
      'internal dest' => ['/node/1', '', '?destination=%2Fnode%2F1'],
      'internal destination' => ['', '/admin/content', '?destination=%2Fadmin%2Fcontent'],
      'destination overrides dest' => ['/a', '/b', '?destination=%2Fb'],
      'external dest rejected' => ['https://evil.com', '', ''],
      'external destination rejected' => ['', 'https://evil.com', ''],
      'both empty' => ['', '', ''],
    ];
  }

  /**
   * Tests the moderated content redirect query injects the default domain.
   */
  public function testModeratedContentRedirectQueryAddsDefault(): void {
    $result = $this->invoke('moderatedContentRedirectQuery', [['page' => '2']]);
    $this->assertSame('page=2&field_domain_access_target_id=oit_colorado_edu', $result);
  }

  /**
   * Tests an empty query still yields the default domain target.
   */
  public function testModeratedContentRedirectQueryEmptyQuery(): void {
    $result = $this->invoke('moderatedContentRedirectQuery', [[]]);
    $this->assertSame('field_domain_access_target_id=oit_colorado_edu', $result);
  }

  /**
   * Sets up the container with a current user and messenger for the warning.
   *
   * @param array $roles
   *   The roles the mocked current user should report.
   * @param \PHPUnit\Framework\MockObject\MockObject|null $messenger
   *   An optional messenger mock to register; one is created when omitted.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The messenger mock registered in the container.
   */

  /**
   * Rebuilds the hooks object with a current user and messenger for warnings.
   *
   * @param array $roles
   *   The roles the mocked current user should report.
   * @param \PHPUnit\Framework\MockObject\MockObject|null $messenger
   *   An optional messenger mock to use; one is created when omitted.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The messenger mock injected into the hooks object.
   */
  protected function setUpWarningContainer(array $roles, $messenger = NULL): MessengerInterface {
    $messenger = $messenger ?? $this->createMock(MessengerInterface::class);
    $this->hooks = $this->buildHooks($roles, $messenger);

    return $messenger;
  }

  /**
   * Returns a UTC DateTime inside the deployment window (Wed 17:00 UTC).
   *
   * @return \DateTimeImmutable
   *   A time that falls within the Wednesday 1600–2200 UTC window.
   */
  protected static function windowTime(): \DateTimeImmutable {
    return new \DateTimeImmutable('2025-06-18 17:00:00', new \DateTimeZone('UTC'));
  }

  /**
   * Tests the warning shows on a webform node during the deployment window.
   */
  public function testSecurityWindowWarningShowsOnWebformNode(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($this->anything(), 'warning');
    $this->setUpWarningContainer(['administrator'], $messenger);

    $form = ['#webform_id' => 'contact'];
    $this->invoke('securityWindowWarning', [&$form, 'webform_submission_contact_add_form', self::windowTime()]);
  }

  /**
   * Tests the warning shows on a webform entity form during the window.
   */
  public function testSecurityWindowWarningShowsOnWebformEntity(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addMessage')
      ->with($this->anything(), 'warning');
    $this->setUpWarningContainer(['pseudo_admin'], $messenger);

    $form = [];
    $this->invoke('securityWindowWarning', [&$form, 'webform_settings_form', self::windowTime()]);
  }

  /**
   * Tests no warning is shown when the form is not a webform.
   */
  public function testSecurityWindowWarningSkipsNonWebform(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('addMessage');
    $this->setUpWarningContainer(['administrator'], $messenger);

    $form = [];
    $this->invoke('securityWindowWarning', [&$form, 'node_page_form', self::windowTime()]);
  }

  /**
   * Tests no warning is shown to users without an allowed role.
   */
  public function testSecurityWindowWarningSkipsUnprivilegedRole(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('addMessage');
    $this->setUpWarningContainer(['authenticated'], $messenger);

    $form = ['#webform_id' => 'contact'];
    $this->invoke('securityWindowWarning', [&$form, 'webform_submission_contact_add_form', self::windowTime()]);
  }

  /**
   * Tests no warning is shown outside the deployment window.
   */
  #[DataProvider('outsideWindowProvider')]
  public function testSecurityWindowWarningSkipsOutsideWindow(string $time): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('addMessage');
    $this->setUpWarningContainer(['administrator'], $messenger);

    $now = new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    $form = ['#webform_id' => 'contact'];
    $this->invoke('securityWindowWarning', [&$form, 'webform_submission_contact_add_form', $now]);
  }

  /**
   * Data provider for times outside the deployment window.
   *
   * @return array
   *   Test cases keyed by description.
   */
  public static function outsideWindowProvider(): array {
    return [
      'tuesday in hours' => ['2025-06-17 17:00:00'],
      'wednesday before window' => ['2025-06-18 15:59:00'],
      'wednesday at window end' => ['2025-06-18 22:00:00'],
      'thursday in hours' => ['2025-06-19 17:00:00'],
    ];
  }

  /**
   * Tests loginWords embeds the multipass SVG and attaches its library.
   */
  public function testLoginWordsEmbedsSvg(): void {
    $build = $this->invoke('loginWords', [NULL]);

    $this->assertSame('inline_template', $build['#type']);
    $this->assertSame(['oit/login_multipass'], $build['#attached']['library']);
    $this->assertSame(-10, $build['#weight']);
    $this->assertNull($build['#context']['text']);
    $this->assertStringContainsString('<svg id="multipass"', (string) $build['#context']['svg']);
    $this->assertStringContainsString('class="multipass"', (string) $build['#context']['svg']);
  }

  /**
   * Tests loginWords passes optional text through to the template context.
   */
  public function testLoginWordsWithText(): void {
    $text = $this->getStringTranslationStub()->translate('Click below to login');
    $build = $this->invoke('loginWords', [$text]);

    $this->assertSame($text, $build['#context']['text']);
  }

  /**
   * Tests loginWords renders a SAML link carrying the login destination.
   */
  #[DataProvider('samlLinkProvider')]
  public function testLoginWordsSamlLink(array $query, string $expected): void {
    $stack = new RequestStack();
    $stack->push(new Request($query));
    $hooks = $this->buildHooks([], NULL, $stack);
    $ref = new \ReflectionMethod($hooks, 'loginWords');
    $ref->setAccessible(TRUE);
    $build = $ref->invokeArgs($hooks, [NULL, TRUE]);

    $this->assertSame($expected, $build['#context']['saml_url']);
    $this->assertSame(['url.query_args'], $build['#cache']['contexts']);
  }

  /**
   * Data provider for SAML login link destinations.
   *
   * @return array
   *   Test cases.
   */
  public static function samlLinkProvider(): array {
    return [
      'no destination' => [[], '/saml/login'],
      'destination param' => [['destination' => '/node/5'], '/saml/login?destination=%2Fnode%2F5'],
      'legacy dest param' => [['dest' => '/node/5'], '/saml/login?destination=%2Fnode%2F5'],
      'destination wins' => [['dest' => '/a', 'destination' => '/b'], '/saml/login?destination=%2Fb'],
      'external rejected' => [['destination' => 'https://evil.example.com'], '/saml/login'],
    ];
  }

  /**
   * Tests loginWords omits the SAML link when it is suppressed.
   */
  public function testLoginWordsWithoutSamlLink(): void {
    $build = $this->invoke('loginWords', [NULL, FALSE]);

    $this->assertSame('', $build['#context']['saml_url']);
  }

}
