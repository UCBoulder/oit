<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\oit\Hook\FormHooks;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Asserts every field formAlter() touches has a matching field config.
 *
 * Replaces the dropped 'FormAlterKernelTest' (spec section 6, "Decision:
 * option 1"). formAlter() writes to keys such as
 * $form['field_oit_category']['#group'], and writing to a missing array key
 * silently creates it, so no Unit test of formAlter() itself can fail
 * because of a renamed or removed field (spec section 5.2, "Limit of Unit
 * here"). This test closes that gap by checking the site's exported
 * 'field.field.node.<bundle>.<name>.yml' config directly, with no database
 * and no kernel.
 *
 * The custom modules live in separate git repositories, so a standalone
 * checkout of this module has no 'config/default' directory. The test
 * skips rather than fails in that case (spec section 6.1).
 *
 * Five field names formAlter() touches have no matching config anywhere in
 * 'config/default' today ('field_faq', 'field_faq_section_title' on the
 * node_page_form 'service' sub-type branch, and
 * 'field_oit_page_file_attatchment', 'field_oit_news_front_image',
 * 'field_oit_page_related_content' on the node_news_form branch). That is a
 * pre-existing defect reported separately, not asserted here: those five
 * names are deliberately excluded from the provider below rather than
 * encoded as an expected failure, per instructions not to assert buggy
 * behaviour silently.
 */
#[Group('oit')]
#[CoversMethod(FormHooks::class, 'formAlter')]
class FormAlterFieldConfigExistenceTest extends DrupalUnitTestCase {

  /**
   * Returns the site's 'config/default' directory, or NULL when absent.
   *
   * @return string|null
   *   The absolute path, or NULL when the directory does not exist.
   */
  protected function configDefaultDir(): ?string {
    // Hook -> Unit -> src -> tests -> oit -> custom -> modules -> docroot
    // -> project root.
    $project_root = dirname(__DIR__, 8);
    $dir = $project_root . '/config/default';

    return is_dir($dir) ? $dir : NULL;
  }

  /**
   * Tests every formAlter()-touched field has a matching field config.
   */
  #[DataProvider('touchedFieldProvider')]
  public function testFieldConfigExists(string $bundle, string $field): void {
    $dir = $this->configDefaultDir();
    if ($dir === NULL) {
      $this->markTestSkipped("config/default is not present in this checkout; this module's custom fields live in a separate site repository.");
    }

    $file = $dir . '/field.field.node.' . $bundle . '.' . $field . '.yml';
    $this->assertFileExists($file);
  }

  /**
   * Data provider of bundle/field pairs formAlter() touches.
   *
   * Field-to-group maps mirror the switch($form_id) branches in
   * FormHooks::formAlter(): node_page_form, node_service_alert_form,
   * node_news_form, node_webform_form and node_tutorial_form.
   *
   * @return array
   *   Test cases keyed 'bundle:field'.
   */
  public static function touchedFieldProvider(): array {
    $cases = [];

    $page_fields = [
      'field_oit_category',
      'field_access_control_2',
      'field_show_child_links',
      'upload',
      'field_dl_facstaff',
      'field_dl_student',
      'field_dl_authenticated',
      'taxonomy_vocabulary_11',
      'field_service_main_page',
      'field_services_related',
      'field_tut_comp_type_d7',
      'field_software_download_link',
    ];
    foreach ($page_fields as $field) {
      $cases['page:' . $field] = ['page', $field];
    }

    $service_alert_fields = ['field_access_control_2', 'field_sympa_send'];
    foreach ($service_alert_fields as $field) {
      $cases['service_alert:' . $field] = ['service_alert', $field];
    }

    // field_oit_page_file_attatchment, field_oit_news_front_image and
    // field_oit_page_related_content are excluded: no matching config
    // exists anywhere in config/default. See the class docblock.
    $news_fields = [
      'field_oit_category',
      'field_access_control_2',
      'taxonomy_vocabulary_11',
      'field_sympa_send',
    ];
    foreach ($news_fields as $field) {
      $cases['news:' . $field] = ['news', $field];
    }

    $webform_fields = ['field_access_control_2', 'field_oit_category'];
    foreach ($webform_fields as $field) {
      $cases['webform:' . $field] = ['webform', $field];
    }

    $tutorial_fields = [
      'field_access_control_2',
      'field_oit_category',
      'taxonomy_vocabulary_11',
      'field_tut_comp_type_d7',
      'upload',
    ];
    foreach ($tutorial_fields as $field) {
      $cases['tutorial:' . $field] = ['tutorial', $field];
    }

    return $cases;
  }

}
