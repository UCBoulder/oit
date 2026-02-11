(function ($) {
  $(document).ready(function () {
    if ( $( ".table-search" ).length ) {
      $('.table-search').each(function (i, elem) {
        var $ts = $(elem);
        var tableId = $ts.next().attr('id');
        var inputId = 'searchtable-' + i;
        $ts.before('<label for="' + inputId + '"><strong>Enter keyword to search </strong></label><input id="' + inputId + '" type="search" autosave="csrsearch" results="1" placeholder="search" name="s">');
        $('#' + inputId).keyup(function () {
          searchTable($(this).val(), tableId);
        });

        if ( $( ".page-node-1485" ).length ) {
          var selectHtml = '<select class="cat_search" style="margin-left: .5em;" name="cat"><option value="">Choose From Categories</option><option value="">--- Room Features ---</option><option value="RCC">Remote-Capable Classrooms (RCC)</option><option value="ME ">Media Equipped (ME)</option><option value="SC">Smart Classrooms (SC)</option><option value="ALR">Active Learning Rooms (ALR)</option><option value="LLH">Large Lecture Hall (LLH)</option><option value="DL">Distance Learning (DL)</option><option value="CC">Classroom Capture (CC)</option><option value="CR">CUClickers Receivers (CR)</option><option value="WM">Wireless Microphones (WM)</option><option value="LAB">Computing Labs (LAB)</option><option value="">--- Building Type ---</option><option value="academic">Academic</option></select>';
          $ts.before(selectHtml);
          $ts.prev('.cat_search').change(function () {
            var selection = this.value;
            if (selection) {
              $('#' + inputId).val(selection);
              searchTable(selection, tableId);
            } else {
              $('#' + inputId).val('');
              searchTable(selection, tableId);
            }
          }).change();
        }
      });
    }
  });

  function searchTable(inputVal, tableId) {
    var table = $('#' + tableId);
    table.find('tr').each(function (index, row) {
      var allCells = $(row).find('td');
      var found = 0; // Declare found here, outside the inner each function
      if (allCells.length > 0) {
        allCells.each(function (index, td) {
          var regExp = new RegExp(inputVal, 'i');
          if (regExp.test($(td).text())) {
            found = 1; // Set found to true if a match is found
            return; // Break out of the each loop
          }
        });
        if (found == 1) {
          $(row).show();
        } else {
          $(row).hide();
        }
      }
    });
  }

}(jQuery));
