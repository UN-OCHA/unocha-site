(function (Drupal) {

  'use strict';

  /**
   * Update the number of selected items in the button pane.
   *
   * @param {number} selected
   *   The number of selected items.
   * @param {number} limit
   *   Maximum number of selectable items.
   */
  function updateSelectionCount(dialog, selected, limit) {
    let text = '';

    // Do not show the upper limit if an unlimited selection is allowed.
    if (limit < 0) {
      text = Drupal.formatPlural(selected, '1 item selected', '@count items selected');
    }
    else {
      text = Drupal.formatPlural(limit, '@selected of @count item selected', '@selected of @count items selected', {
        '@selected': selected
      });
    }

    dialog.querySelector('.js-media-library-selected-count').textContent = text;
  }

  /**
   * Show the canto library in an iframe.
   *
   * @param {node} button
   *   The "select from canto" button element.
   * @param {object} settings
   *   Settings passed to the Drupal behaviors.
   */
  function showCantoLibrary(button, settings) {
    // Get the parent dialog.
    const dialog = button.closest('.ui-dialog');

    // Elements to store the JSON data of the assets to insert.
    const assets = dialog.querySelector('[data-drupal-selector="edit-canto-assets"]');

    // Ensure we do not leave any left over of the previously loaded assets.
    assets.value = '';

    // Empty the current selection.
    if (Drupal.MediaLibrary && Drupal.MediaLibrary.currentSelection) {
      Drupal.MediaLibrary.currentSelection = [];
    }

    // Canto library iframe.
    const iframe = document.createElement('iframe');
    iframe.setAttribute('class', 'canto-library');
    iframe.setAttribute('src', '/canto/assets/library.html');

    // Get the maximum number of selectable items.
    const limit = settings.media_library.selection_remaining;

    // Get the upload button. We'll trigger its click event so that the
    // form can be submitted via ajax.
    const upload = dialog.querySelector('[data-drupal-selector="edit-upload-upload-button"]');

    // Listen to message events from the iframe to be created to retrieve
    // the verification code to exchange for an access token.
    window.addEventListener('message', function (event) {
      if (event.source !== iframe.contentWindow) {
        return;
      }

      const {type, data} = typeof event.data === 'object' ? event.data : {};

      switch (type) {
        // Initialize the library when the iframe is ready.
        // @todo that a good place to pass options to the iframe like the
        // allowed content types.
        case 'ready':
          event.source.postMessage({
            type: 'initialize',
            data: {
              // @todo retrieve that from the settings set via the widget
              // settings?
              selectionLimit: limit,
              allowedExtensions: ['jpg', 'jpeg', 'png'],
              scheme: 'image'
            }
          // @todo pass a proper origin.
          }, window.origin);
          break;

        case 'updateSelection':
          updateSelectionCount(button.closest('.ui-dialog'), data.length, limit);
          assets.value = JSON.stringify(data);
          break;
      }
    });

    // Take over the submission of the media library so we can call our
    // own callback to create media from the selected items.
    // @todo hide the button and add a new one saying "Add selected items".
    const select = dialog.querySelector('.ui-dialog-buttonset .media-library-select');

    const add = select.cloneNode(false);
    add.setAttribute('type', 'button');
    add.setAttribute('id', 'canto-library-add');
    add.setAttribute('name', 'canto-library-add');
    add.appendChild(document.createTextNode(Drupal.t('Add selected')));
    add.addEventListener('click', function (event) {
      upload.dispatchEvent(new Event('mousedown'));
      upload.dispatchEvent(new Event('mouseup'));
      upload.dispatchEvent(new Event('click'));
    });

    // Hide the select button and insert the add one instead.
    select.setAttribute('hidden', '');
    select.parentNode.insertBefore(add, select);

    // Hide the current dialog content and show the iframe instead.
    const mediaLibraryView = dialog.querySelector('#media-library-view');
    mediaLibraryView.setAttribute('hidden', '');

    const mediaLibraryAddForm = dialog.querySelector('.media-library-add-form');
    mediaLibraryAddForm.setAttribute('hidden', '');
    mediaLibraryAddForm.parentNode.insertBefore(iframe, mediaLibraryAddForm);
  }

  /**
   * Drupal behavio for the Canto library.
   */
  Drupal.behaviors.cantoLibrary = {
    attach: function (context, settings) {
      // Get the "Select from Canto" button.
      const button = context.querySelector('[data-drupal-selector="edit-canto-select"]');

      // Skip if we already processed the button.
      if (!button || button.hasAttribute('data-canto-library-processed')) {
        return;
      }

      // Mark the button as processed so the following code runs only once.
      button.setAttribute('data-canto-library-processed', '');


      // Replace the media library with the Canto libray.
      button.addEventListener('click', function (event) {
        event.preventDefault();

        showCantoLibrary(button, settings);
      });
    }
  };

})(Drupal);
