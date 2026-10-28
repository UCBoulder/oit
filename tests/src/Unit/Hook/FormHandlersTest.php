<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\FormHooks;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the FormHooks dashboard category, GP body and news handlers.
 */
#[Group('oit')]
#[CoversMethod(FormHooks::class, 'dashboardCategoryAddSubmit')]
#[CoversMethod(FormHooks::class, 'dashboardCategoryDeleteSubmit')]
#[CoversMethod(FormHooks::class, 'gpBodyValidate')]
#[CoversMethod(FormHooks::class, 'newsCategoriesValidate')]
class FormHandlersTest extends DrupalUnitTestCase {

  /**
   * In-memory backing store for the mocked state service.
   *
   * @var array
   */
  protected array $stateValues = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Sets a container whose state service reads and writes $stateValues.
   *
   * @param array $values
   *   Initial state values, keyed by state key.
   */
  protected function setState(array $values = []): void {
    $this->stateValues = $values;
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(fn ($key, $default = NULL) => $this->stateValues[$key] ?? $default);
    $state->method('set')->willReturnCallback(function ($key, $value): void {
      $this->stateValues[$key] = $value;
    });
    $container = new ContainerBuilder();
    $container->set('state', $state);
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for testDashboardCategoryAddSubmit().
   *
   * @return array
   *   Rows of [initial state, submitted name, expected state].
   */
  public static function addProvider(): array {
    return [
      'no existing state' => [NULL, 'Email', '["Email"]'],
      'appends to existing state' => ['["Email"]', 'Canvas', '["Email","Canvas"]'],
      'filters markup from the name' => [NULL, '<script>x</script>Zoom', '["xZoom"]'],
    ];
  }

  /**
   * Tests the submitted term name is queued in oit_dashboard_add.
   */
  #[DataProvider('addProvider')]
  public function testDashboardCategoryAddSubmit(?string $initial, string $name, string $expected): void {
    $this->setState($initial === NULL ? [] : ['oit_dashboard_add' => $initial]);
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('name', [['value' => $name]]);

    FormHooks::dashboardCategoryAddSubmit($form, $form_state);

    $this->assertSame($expected, $this->stateValues['oit_dashboard_add']);
    $this->assertArrayNotHasKey('oit_dashboard_delete', $this->stateValues);
  }

  /**
   * Data provider for testDashboardCategoryDeleteSubmit().
   *
   * @return array
   *   Rows of [initial state, term name, expected state].
   */
  public static function deleteProvider(): array {
    return [
      'no existing state' => [NULL, 'Email', '["Email"]'],
      'appends to existing state' => ['["Email"]', 'Canvas', '["Email","Canvas"]'],
    ];
  }

  /**
   * Tests the deleted term's name is queued in oit_dashboard_delete.
   */
  #[DataProvider('deleteProvider')]
  public function testDashboardCategoryDeleteSubmit(?string $initial, string $name, string $expected): void {
    $this->setState($initial === NULL ? [] : ['oit_dashboard_delete' => $initial]);
    $term = $this->createMock(TermInterface::class);
    $term->method('getName')->willReturn($name);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($term);
    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form = [];

    FormHooks::dashboardCategoryDeleteSubmit($form, $form_state);

    $this->assertSame($expected, $this->stateValues['oit_dashboard_delete']);
    $this->assertArrayNotHasKey('oit_dashboard_add', $this->stateValues);
  }

  /**
   * Builds a form state for an edit form of a node with the given body.
   *
   * @param bool $is_new
   *   Whether the node is new.
   * @param string|null $old_body
   *   The node's saved body value.
   * @param string $new_body
   *   The submitted body value.
   *
   * @return \Drupal\Core\Form\FormState
   *   The form state.
   */
  protected function buildGpFormState(bool $is_new, ?string $old_body, string $new_body): FormState {
    $body_field = $this->createMock(FieldItemListInterface::class);
    $body_field->method('__get')->with('value')->willReturn($old_body);
    $node = $this->createMock(NodeInterface::class);
    $node->method('isNew')->willReturn($is_new);
    $node->method('get')->with('body')->willReturn($body_field);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($node);
    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form_state->setValue('body', [['value' => $new_body]]);
    return $form_state;
  }

  /**
   * Data provider for testGpBodyValidate().
   *
   * @return array
   *   Rows of [is new, old body, new body, expect error].
   */
  public static function gpProvider(): array {
    return [
      'emptying an existing body' => [FALSE, '<p>Old</p>', '', TRUE],
      'whitespace-only new body' => [FALSE, '<p>Old</p>', "  \n ", TRUE],
      'changing the body' => [FALSE, '<p>Old</p>', '<p>New</p>', FALSE],
      'empty to empty' => [FALSE, '', '', FALSE],
      'null old body to empty' => [FALSE, NULL, '', FALSE],
      'new node with empty body' => [TRUE, '<p>Old</p>', '', FALSE],
    ];
  }

  /**
   * Tests a body error is set only when an existing body is emptied.
   */
  #[DataProvider('gpProvider')]
  public function testGpBodyValidate(bool $is_new, ?string $old_body, string $new_body, bool $expect_error): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $form = [];
    $form_state = $this->buildGpFormState($is_new, $old_body, $new_body);

    FormHooks::gpBodyValidate($form, $form_state);

    if ($expect_error) {
      $this->assertSame(['body'], array_keys($form_state->getErrors()));
      $this->assertSame(
        'You went from having body content to none. As a hall monitor, I will not allow such tomfoolery.',
        (string) $form_state->getErrors()['body']
      );
    }
    else {
      $this->assertSame([], $form_state->getErrors());
    }
  }

  /**
   * Data provider for testNewsCategoriesValidate().
   *
   * @return array
   *   Rows of [term id => parent id map or NULL, expected error or NULL].
   */
  public static function newsCategoriesProvider(): array {
    return [
      'no categories' => [NULL, 'Please select a Service &amp; News category'],
      'empty categories' => [[], 'Please select a Service &amp; News category'],
      'service only' => [[10 => '885'], 'Please select a News category'],
      'news only' => [[20 => '889'], 'Please select a Service category'],
      'unrelated parent only' => [[30 => '1'], 'Please select a Service &amp; News category'],
      'service and news' => [[10 => '885', 20 => '889'], NULL],
      'integer parent ids' => [[10 => 885, 20 => 889], NULL],
      'service, news and unrelated' => [[10 => '885', 30 => '1', 20 => '889'], NULL],
    ];
  }

  /**
   * Tests news needs a category under both the Service and News parents.
   */
  #[DataProvider('newsCategoriesProvider')]
  public function testNewsCategoriesValidate(?array $parents, ?string $expected): void {
    $terms = [];
    foreach ($parents ?? [] as $tid => $parent_id) {
      $term = $this->createMock(Term::class);
      $term->method('__get')->with('parent')->willReturn((object) ['target_id' => $parent_id]);
      $terms[$tid] = $term;
    }
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(fn ($tid) => $terms[$tid] ?? NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('taxonomy_term')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $form = [];
    $form_state = new FormState();
    if ($parents !== NULL) {
      $form_state->setValue('field_oit_category', array_map(fn ($tid) => ['target_id' => $tid], array_keys($parents)));
    }

    FormHooks::newsCategoriesValidate($form, $form_state);

    if ($expected === NULL) {
      $this->assertSame([], $form_state->getErrors());
    }
    else {
      $this->assertSame(['field_oit_category'], array_keys($form_state->getErrors()));
      $this->assertSame($expected, (string) $form_state->getErrors()['field_oit_category']);
    }
  }

}
