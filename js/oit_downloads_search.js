(function ($) {
  $(document).ready(function () {
    if ($("ul").is('.list')){
      if($('input.search').length === 0){
        $('ul.list dl').addClass('downloads');
        $('ul.list').before('<input class="search" name="s" type="search" placeholder="search" results="5" type="search" /> <select class="search"><option value="">Choose From Categories</option><option value="">--- Affiliation ---</option><option value="faculty">Faculty</option><option value="Faculty/Staff">Faculty/Staff</option><option value="student">Students</option><option value="">--- Campus ---</option><option value="boulder">Boulder</option><option value="colorado springs">Colorado Springs</option><option value="denver">Denver</option><option value="">--- Operating System ---</option><option value="linux">Linux</option><option value="mac">Mac OS X</option><option value="windows">Windows</option><option value="">--- License Type ---</option><option value="no fee">no fee</option><option value="online purchase">online purchase, CU Book Store or Marketplace</option><option value="special rates">special rates</option></select>');
      }
    }
    // Implments list.js library for searching
    var options = {
      valueNames: [ 'downloads' ]
    };
    var appList = new List('apps', options);
    // Take dropdown selection to search text box
    $("select.search").change(function () {
      var searchFilter = $(this).find('option:selected').attr('value');
      $('.search').val(searchFilter);
      appList.search(searchFilter);
    });

  });
}(jQuery));
