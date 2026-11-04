/**
 * @file
 * Opens the Coffee pop-up from the Navigation sidebar "Go to" button.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.oitNavigationCoffee = {
    attach: function (context) {
      once('oit-coffee-trigger', '.oit-coffee-trigger', context).forEach(function (el) {
        $(el).on('click', function (event) {
          event.preventDefault();
          // Coffee keeps its API private, so emulate its alt+d shortcut.
          $(document).trigger($.Event('keydown', { altKey: true, keyCode: 68, which: 68 }));
        });
      });
    }
  };
}(jQuery, Drupal, once));
