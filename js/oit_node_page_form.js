(function ($) {
  $(document).ready(function () {
    $('#edit-field-oit-category').select2();
    $('#edit-field-services-related').select2();
    $('#edit-menu-menu-parent').select2();
    $('span.select2').attr('style', 'width: 100%');
    $(".copy-icon").click(
      function () {
        copyClipboard($(this).attr('data-clipboard'));
      }
    );
  });
}(jQuery));
