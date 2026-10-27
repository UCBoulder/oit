<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\oit\Hook\FormHooks;
use Drupal\oit\Plugin\Domain;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for FormHooks::setDomainSubmit().
 */
#[Group('oit')]
#[CoversMethod(FormHooks::class, 'setDomainSubmit')]
class SetDomainSubmitTest extends DrupalUnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Sets a container whose oit.domain service reports the given domain.
   *
   * @param string $domain
   *   The domain getDomain() returns.
   */
  protected function setDomain(string $domain): void {
    $domain_service = $this->createMock(Domain::class);
    $domain_service->method('getDomain')->willReturn($domain);
    $container = new ContainerBuilder();
    $container->set('oit.domain', $domain_service);
    \Drupal::setContainer($container);
  }

  /**
   * Tests the handler does nothing when the form has no domain set.
   */
  public function testNoDomainOnFormIsNoop(): void {
    // No container is set. If the method reached \Drupal::service() it would
    // fatal here, proving the early return.
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['field_domain_access' => [], 'field_domain_source' => []]);

    FormHooks::setDomainSubmit($form, $form_state);

    $this->assertSame([], $form_state->getValue('field_domain_access'));
    $this->assertSame([], $form_state->getValue('field_domain_source'));
  }

  /**
   * Data provider for testForcesDomainWhenEmpty().
   *
   * @return array
   *   Rows of [domain name, domain id, access value, source value].
   */
  public static function emptyValuesProvider(): array {
    return [
      'oit, both empty' => ['oit', 'oit_colorado_edu', [], []],
      'oit, source empty' => ['oit', 'oit_colorado_edu', [['target_id' => 'x']], []],
      'oit, access empty' => ['oit', 'oit_colorado_edu', [], [['target_id' => 'x']]],
      'oda, both empty' => ['oda', 'oda_colorado_edu', [], []],
    ];
  }

  /**
   * Tests access and source are forced when either is empty.
   */
  #[DataProvider('emptyValuesProvider')]
  public function testForcesDomainWhenEmpty(string $name, string $id, array $access, array $source): void {
    $this->setDomain($name);
    $form = ['#oit_domain' => ['name' => $name, 'id' => $id]];
    $form_state = new FormState();
    $form_state->setValues(['field_domain_access' => $access, 'field_domain_source' => $source]);

    FormHooks::setDomainSubmit($form, $form_state);

    $this->assertSame(['target_id' => $id], $form_state->getValue('field_domain_access'));
    $this->assertSame(['target_id' => $id], $form_state->getValue('field_domain_source'));
  }

  /**
   * Tests values are left alone when both are already set.
   */
  public function testLeavesValuesWhenBothSet(): void {
    $this->setDomain('oit');
    $form = ['#oit_domain' => ['name' => 'oit', 'id' => 'oit_colorado_edu']];
    $form_state = new FormState();
    $values = [['target_id' => 'oda_colorado_edu']];
    $form_state->setValues(['field_domain_access' => $values, 'field_domain_source' => $values]);

    FormHooks::setDomainSubmit($form, $form_state);

    $this->assertSame($values, $form_state->getValue('field_domain_access'));
    $this->assertSame($values, $form_state->getValue('field_domain_source'));
  }

  /**
   * Tests values are left alone when the site domain does not match.
   */
  public function testLeavesValuesOnOtherDomain(): void {
    $this->setDomain('oda');
    $form = ['#oit_domain' => ['name' => 'oit', 'id' => 'oit_colorado_edu']];
    $form_state = new FormState();
    $form_state->setValues(['field_domain_access' => [], 'field_domain_source' => []]);

    FormHooks::setDomainSubmit($form, $form_state);

    $this->assertSame([], $form_state->getValue('field_domain_access'));
    $this->assertSame([], $form_state->getValue('field_domain_source'));
  }

}
