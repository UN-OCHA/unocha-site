/**
 * HR.info iFrame embeds.
 */
(function iife() {
  var autoIframes = document.querySelectorAll('.hri-iframe--ratio-auto');

  // Did we find iframes with the 'auto' aspect-ratio setting?
  if (autoIframes) {
    // For every iframe found, set its CSS aspect-ratio using the attributes
    // on the container element: data-width, data-height.
    autoIframes.forEach(function (el) {
      el.style.aspectRatio = el.dataset.width +' / '+ el.dataset.height;
    });
  }
})();
