/**
 * @file
 * Scrolls to the element matching the window location hash after a delay.
 *
 * This is needed because dynamic modification of the form (e.g., by AJAX)
 * can shift the scroll position, so we need to wait for the form to settle
 * before scrolling to the target element.
 */
(function (Drupal) {
  'use strict';

  /**
   * Behavior to scroll to hash fragment after form modifications.
   */
  Drupal.behaviors.nodeFormHashScroll = {
    attach: function (context, settings) {
      // Only process on initial page load (when context is the document).
      if (context !== document) {
        return;
      }

      // Only process if there's a hash in the URL.
      if (!window.location.hash) {
        return;
      }

      var hash = window.location.hash;
      if (!/^#paragraph-[0-9a-fA-F-]{36}$/.test(hash)) {
        return;
      }

      // Check if we've already processed this hash to avoid re-scrolling.
      var uuid = hash.replace('#paragraph-', '');
      var processedKey = 'data-node-form-hash-scroll-processed';
      if (document.body.hasAttribute(processedKey)) {
        return;
      }

      // Mark as processed.
      document.body.setAttribute(processedKey, 'true');

      // Delay scrolling to allow JavaScript to finis(h modifying the form.
      setTimeout(function () {
        var element = document.querySelector(`[data-paragraph-uuid="${uuid}"]`);
        if (element) {
          element.setAttribute('tabindex', '-1');
          element.scrollIntoView({behavior: 'smooth', block: 'center'});
          element.focus({preventScroll: true});
        }
      }, 500);
    }
  };

})(Drupal);
