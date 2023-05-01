(function ($, Drupal) {

  'use strict';

  // Override of core/dialog.ajax::prepareDialogButtons() to prevent moving
  // buttons into the button pane aside of the main dialog buttons to improve
  // the UX and accessibility by keeping other buttons at their original place.
  //
  // @see https://www.drupal.org/project/drupal/issues/3089751
  Drupal.behaviors.dialog.prepareDialogButtons = function ($dialog) {
    // Limit to the main dialog edit buttons. Aside of that this is the main
    // code as core/misc/dialog/dialog.ajax.js as of Drupal 10.0.8.
    const selectors = [
      '.form-actions[data-drupal-selector="edit-actions"] input[type=submit]',
      '.form-actions[data-drupal-selector="edit-actions"] a.button'
    ].join(', ');
    const buttons = [];
    const $buttons = $dialog.find(selectors);
    $buttons.each(function () {
      const $originalButton = $(this).css({display: 'none'});
      buttons.push({
        text: $originalButton.html() || $originalButton.attr('value'),
        class: $originalButton.attr('class'),
        click(e) {
          // If the original button is an anchor tag, triggering the "click"
          // event will not simulate a click. Use the click method instead.
          if ($originalButton.is('a')) {
            $originalButton[0].click();
          }
          else {
            $originalButton
            .trigger('mousedown')
            .trigger('mouseup')
            .trigger('click');
            e.preventDefault();
          }
        }
      });
    });
    return buttons;
  };

})(jQuery, Drupal);
