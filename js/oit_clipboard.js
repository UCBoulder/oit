(function (Drupal) {
  Drupal.oit = Drupal.oit || {};

  /**
   * Copy a canned HTML snippet (or arbitrary text) to the clipboard.
   *
   * @param {string} text
   *   One of the known snippet keys (flexBox, details, details-no-deets,
   *   shortcode-block), or any other string to copy verbatim.
   */
  Drupal.oit.copySnippet = function (text) {
    var el = document.createElement('textarea');
    if (text == 'flexBox') {
      el.value = "<div class='flex'>\n<div class='flex-one-third'>1/3</div>\n<div class='flex-one-third'>1/3</div>\n<div class='flex-one-third'>1/3</div>\n<div class='flex-one-half'>1/2</div>\n<div class='flex-one-half'>1/2</div>\n<div class='flex-three-quarters'>3/4</div>\n<div class='flex-one-quarter'>1/4</div>\n</div>";
    } else if (text == 'details') {
      el.value = "<details><summary>Summary</summary>Details here</details>";
    } else if (text == 'details-no-deets') {
      el.value = "<details class='no-deets-controls'><summary>Summary</summary>Details here</details>";
    } else if (text == 'shortcode-block') {
      el.value = "[block id='block-id'][/block]";
    } else {
      el.value = text;
    }

    el.setAttribute('readonly', '');
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
  };

}(Drupal));
