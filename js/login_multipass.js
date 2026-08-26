/**
 * @file
 * Animates the multipass SVG on the login page.
 *
 * The SVG is rendered server side by the user_login_form alter in
 * Drupal\oit\Hook\FormHooks. The .multipass group sits below the viewBox and
 * slides into view on hover.
 */

(function (Drupal, once) {
  Drupal.behaviors.oitLoginMultipass = {
    attach: function (context) {
      once('oit-login-multipass', '#multipass', context).forEach(function (svg) {
        var iam = gsap.timeline({ repeat: 0, paused: true });
        iam.to('.multipass', { y: -185, x: -100, duration: 0.8 });

        svg.addEventListener('mouseover', function () {
          iam.play();
        });
        svg.addEventListener('mouseleave', function () {
          iam.reverse();
        });
      });
    }
  };
})(Drupal, once);
