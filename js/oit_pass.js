(function ($) {
  $(document).ready(function () {

    // Show the 'none shall pass' image on access denied.
    $('body').keydown(function (e) {
      if(e.which == 49) {
        $('#myprecious').show();
      }
    });
    // Prevents this from working in the search box.
    $('input').keydown(function (event) {
      event.stopPropagation();
    });

  });
}(jQuery));
