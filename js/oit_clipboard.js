function copyClipboard(text) {
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
  el.style = { position: 'absolute', left: '-9999px' };
  document.body.appendChild(el);
  el.select();
  document.execCommand('copy');
  document.body.removeChild(el);
}
