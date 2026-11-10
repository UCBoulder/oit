/**
 * @file
 * Applies Select2 to tutorial form reference fields in the Gin theme.
 */

(function ($, Drupal, once) {
  /**
   * Initializes Select2 on tutorial form reference fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.oitGinSelect2 = {
    attach(context) {
      if (!$.fn.select2) {
        return;
      }
      $(
        once(
          'oit-gin-select2',
          '#edit-field-tut-ref-service, #edit-field-tut-ref-tut, #edit-field-oit-category, #edit-field-tut-back-btn',
          context,
        ),
      ).select2();
    },
  };
})(jQuery, Drupal, once);
