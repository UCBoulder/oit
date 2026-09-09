<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\oit\Hook\FormHooks;
use Drupal\oit\Plugin\Domain;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Regression tests for the section 3.3 defect fixes reached via formAlter().
 *
 * Only the three formAlter()-reached defects are covered here: missing
 * '#id', a service alert form with no body element, and webform captcha
 * roles missing from config. The rest of formAlter() coverage is Unit 3.
 *
 * Domain::getDomain() is mocked to a non-oit value in every case so the
 * procedural functions from oit.module are never reached.
 */
#[Group('oit')]
#[CoversMethod(FormHooks::class, 'formAlter')]
class FormAlterRegressionTest extends DrupalUnitTestCase {

  /**
   * Builds a FormHooks instance with mocked dependencies.
   *
   * @param array $roles
   *   The roles the mocked current user should report.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   An optional config factory mock; one returning NULL for every config
   *   key is used when omitted.
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $routeMatch
   *   An optional route match mock; one returning no 'node' parameter is
   *   used when omitted.
   *
   * @return \Drupal\oit\Hook\FormHooks
   *   The configured hooks object.
   */
  protected function buildHooks(array $roles = [], ?ConfigFactoryInterface $configFactory = NULL, ?RouteMatchInterface $routeMatch = NULL): FormHooks {
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('getRoles')->willReturn($roles);

    $domain = $this->createMock(Domain::class);
    $domain->method('getDomain')->willReturn('na');

    $resolver = $this->createMock(ExtensionPathResolver::class);
    $resolver->method('getPath')->willReturn(dirname(__DIR__, 4));

    $hooks = new FormHooks(
      $routeMatch ?? $this->createMock(RouteMatchInterface::class),
      $current_user,
      $configFactory ?? $this->buildEmptyConfigFactory(),
      $this->createMock(RequestStack::class),
      $this->createMock(MessengerInterface::class),
      $this->createMock(KillSwitch::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $domain,
      $resolver,
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());

    return $hooks;
  }

  /**
   * Builds a config factory whose configs return NULL for every get().
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The mocked config factory.
   */
  protected function buildEmptyConfigFactory(): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(NULL);
    $config->method('isNew')->willReturn(TRUE);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);

    return $config_factory;
  }

  /**
   * Tests formAlter() with a bare form array, no '#id' key, no warning.
   *
   * Pins the fix for FormHooks.php:161: `$form['#id']` was read
   * unconditionally, warning on any form array without an '#id' key.
   */
  public function testFormAlterMissingIdNoWarning(): void {
    $hooks = $this->buildHooks();
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $hooks->formAlter($form, $form_state, '');

    $this->assertSame([], $form);
  }

  /**
   * Tests a service alert form with no body element raises no warning.
   *
   * Pins the fix for FormHooks.php:229:
   * `$form['body']['widget'][0]['#default_value']` was read unconditionally
   * on the service alert branch.
   */
  public function testFormAlterServiceAlertNoBodyNoWarning(): void {
    $hooks = $this->buildHooks();
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $hooks->formAlter($form, $form_state, 'node_service_alert_form');

    // The guard fills in the template default when body is absent, proving
    // execution continued past the fixed read without a warning.
    $this->assertStringContainsString('<h2>Impact</h2>', $form['body']['widget'][0]['#default_value']);
  }

  /**
   * Tests webform captcha handling when access.create.roles is missing.
   *
   * Pins the fix for FormHooks.php:91: `$webform_roles` from
   * `$config->get('access.create.roles')` was iterated unconditionally.
   * When the config key is missing, `get()` returns NULL and the foreach
   * raises a fatal TypeError on PHP 8.
   */
  public function testFormAlterWebformCaptchaRolesNullNoTypeError(): void {
    $node = new class {

      /**
       * Returns a fixed node id.
       */
      public function id() {
        return 5;
      }

    };
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('node')->willReturn($node);

    $hooks = $this->buildHooks(['administrator'], NULL, $route_match);
    $form = ['#webform_id' => 'testform'];
    $form_state = $this->createMock(FormStateInterface::class);

    $hooks->formAlter($form, $form_state, 'not_a_matching_form_id');

    $this->assertArrayNotHasKey('#prefix', $form);
  }

}
