(function ($, Drupal, once) {
  Drupal.behaviors.oitNodePageForm = {
    attach: function (context) {
      once('oit-node-page-form', 'body', context).forEach(function () {
        $('#edit-field-oit-category').select2();
        $('#edit-field-services-related').select2();
        $('#edit-menu-menu-parent').select2();
        $('span.select2').attr('style', 'width: 100%');
      });

      once('oit-copy', '.oit-copy', context).forEach(function (el) {
        $(el).on('click', function () {
          Drupal.oit.copySnippet($(this).attr('data-clipboard'));
        });
      });
    },
  };
}(jQuery, Drupal, once));
