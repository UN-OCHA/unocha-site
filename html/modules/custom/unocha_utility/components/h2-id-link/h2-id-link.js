/**
 * @file
 * Use the Web Share API on H2.
 */

(function () {
  if (!!navigator.canShare) {
    var h2Titles = document.querySelectorAll('h2[data-ocha-h2-link]');

    h2Titles.forEach((h2Title) => {
      // Create share button.
      let shareButton = document.createElement('button');
      // @TODO: fix non-translatable text
      shareButton.innerHTML = '<span class="visually-hidden">Share</span><img src="/modules/custom/unocha_utility/components/h2-id-link/share.svg" aria-hidden="true" focusable="false">';
      shareButton.classList.add(
        'data-ocha-h2-share-button',
      );

      // Using this as an error, as opposed to success as it appears in the
      // default cd-social-links component.
      shareButton.dataset.message = 'Failed to share';

      // Set up Web Share API when button is clicked.
      shareButton.addEventListener('click', (ev) => {
        let h2Title = ev.target.closest('h2[data-ocha-h2-link]');

        let shareData = {
          title: window.title,
          url: window.location.href + '#' + h2Title.id,
          text: h2Title.nextElementSibling.textContent
        };

        // Hide share button while capturing an image of the figure.
        h2Title.classList.add('is--capturing');

        try {
          // Share.
          if (navigator.canShare(shareData)) {
            navigator.share(shareData);
          }
          else {
            // Share without text.
            delete shareData.text;
            if (navigator.canShare(shareData)) {
              navigator.share(shareData);
            }
            else {
              // Share without title.
              delete shareData.title;
              if (navigator.canShare(shareData)) {
                navigator.share(shareData);
              }
            }
          }
        } catch (err) {
          // Show user feedback and remove after some time.
          shareButton.classList.add('is--showing-message');
          setTimeout(function () {
            shareButton.classList.remove('is--showing-message');
          }, 2500);

          // Log error to console.
          console.error('Sharing failed:', err);
        }

        // Show share button now that image capture was attempted.
        h2Title.classList.remove('is--capturing');
      });

      // Insert button into DOM.
      h2Title.append(shareButton);
      // Mark this figure as shareable.
      h2Title.classList.add('data-ocha-h2-share-button--can-share');
    });
  }
}());

