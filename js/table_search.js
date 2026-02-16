(function ($) {
  $(document).ready(function () {
    if ( $( ".table-search" ).length ) {
      $('.table-search').each(function (i, elem) {
        var $ts = $(elem);
        var tableId = $ts.next().attr('id');
        var inputId = 'searchtable-' + i;
        var clearId = 'clear-' + inputId;
        $ts.before('<div style="position: relative; width: fit-content;"><label for="' + inputId + '"><strong>Enter keyword to search </strong></label><input id="' + inputId + '" autosave="csrsearch" results="1" placeholder="search" name="s"><a href="#" id="' + clearId + '" class="search-clear" style="display:none; position: absolute; right: 5px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 34" class="svg-icon x-skinny" width="10"><use fill="#000" xlink:href="/sites/default/files/svg/sprite_8.svg#x-skinny"></use></svg><span style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">Clear</span></a></div>');

        var $input = $('#' + inputId);
        var $clear = $('#' + clearId);

        $input.keyup(function (e) {
          var val = $(this).val();

          // Check if Escape key was pressed
          if (e.key === 'Escape' || e.keyCode === 27) {
            $(this).val('');
            searchTable('', tableId);
            $clear.hide();
            return;
          }

          searchTable(val, tableId);
          $clear.toggle(val.length > 0);
        });

        $clear.click(function (e) {
          e.preventDefault();
          $input.val('');
          searchTable('', tableId);
          $clear.hide();
          $input.focus();
        });

        if ( $( ".page-node-1485" ).length ) {
          var selectHtml = '<select class="cat_search" style="margin-left: .5em;" name="cat"><option value="">Choose From Categories</option><option value="">--- Room Features ---</option><option value="RCC">Remote-Capable Classrooms (RCC)</option><option value="ME ">Media Equipped (ME)</option><option value="SC">Smart Classrooms (SC)</option><option value="ALR">Active Learning Rooms (ALR)</option><option value="LLH">Large Lecture Hall (LLH)</option><option value="DL">Distance Learning (DL)</option><option value="CC">Classroom Capture (CC)</option><option value="CR">CUClickers Receivers (CR)</option><option value="WM">Wireless Microphones (WM)</option><option value="LAB">Computing Labs (LAB)</option><option value="">--- Building Type ---</option><option value="academic">Academic</option></select>';
          $ts.before(selectHtml);
          $ts.prev('.cat_search').change(function () {
            var selection = this.value;
            if (selection) {
              $input.val(selection);
              searchTable(selection, tableId);
              $clear.show();
            } else {
              $input.val('');
              searchTable(selection, tableId);
              $clear.hide();
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
