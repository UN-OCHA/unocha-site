(function (Drupal) {

  'use strict';

  Drupal.behaviors.unoSectionTabs = {
    attach: function (context, settings) {
      // The tab switching function.
      const switchTab = (tabs, panels, oldTab, newTab) => {
        newTab.focus();
        // Make the active tab focusable by the user (Tab key).
        newTab.removeAttribute('tabindex');
        // Set the selected state
        newTab.setAttribute('aria-selected', 'true');
        oldTab.removeAttribute('aria-selected');
        oldTab.setAttribute('tabindex', '-1');
        // Get the indices of the new and old tabs to find the correct
        // tab panels to show and hide.

        let index = Array.prototype.indexOf.call(tabs, newTab);
        let oldIndex = Array.prototype.indexOf.call(tabs, oldTab);
        panels[oldIndex].hidden = true;
        panels[index].hidden = false;
      };

      // Enhanced tabbed section.
      const enhanceSection = (section) => {
        section.setAttribute('data-uno-section-tabs-processed', '');

        // Get relevant elements and collections.
        const tablist = section.querySelector('.uno-section-tabs ul');
        const tabs = tablist.querySelectorAll('.uno-section-tabs ul a');
        const panels = section.querySelectorAll('[id^="section"]');

        // Add the tablist role to the first <ul> in the .uno-section-tabs container.
        tablist.setAttribute('role', 'tablist');

        // Add semantics are remove user focusability for each tab.
        Array.prototype.forEach.call(tabs, (tab, i) => {
          tab.setAttribute('role', 'tab');
          tab.setAttribute('id', 'tab' + (i + 1));
          tab.setAttribute('tabindex', '-1');
          tab.parentNode.setAttribute('role', 'presentation');

          // Handle clicking of tabs for mouse users.
          tab.addEventListener('click', e => {
            e.preventDefault();
            let currentTab = tablist.querySelector('[aria-selected]');
            if (e.currentTarget !== currentTab) {
              switchTab(tabs, panels, currentTab, e.currentTarget);
            }
          });

          // Handle keydown events for keyboard users.
          tab.addEventListener('keydown', e => {
            // Get the index of the current tab in the tabs node list.
            let index = Array.prototype.indexOf.call(tabs, e.currentTarget);
            // Work out which key the user is pressing and calculate the new
            // tab's index where appropriate.
            let dir = null;
            if (e.which === 37) {
              dir = index - 1;
            }
            else if (e.which === 39) {
              dir = index + 1;
            }
            else if (e.which === 40) {
              dir = 'down';
            }
            if (dir !== null) {
              e.preventDefault();
              // If the down key is pressed, move focus to the open panel,
              // otherwise switch to the adjacent tab.
              if (dir === 'down') {
                panels[i].focus();
              }
              else if (tabs[dir]) {
                switchTab(tabs, panels, e.currentTarget, tabs[dir]);
              }
            }
          });
        });

        // Add tab panel semantics and hide them all.
        Array.prototype.forEach.call(panels, (panel, i) => {
          panel.setAttribute('role', 'tabpanel');
          panel.setAttribute('tabindex', '-1');
          panel.setAttribute('aria-labelledby', tabs[i].id);
          panel.hidden = true;
        });

        // Initially activate the first tab and reveal the first tab panel.
        tabs[0].removeAttribute('tabindex');
        tabs[0].setAttribute('aria-selected', 'true');
        panels[0].hidden = false;
      };

      // Enhance tabbed sections.
      const sections = document.querySelectorAll('.uno-section-tabs:not([data-uno-section-tabs-processed])');
      if (sections) {
        Array.prototype.forEach.call(sections, enhanceSection);
      }
    }
  };
})(Drupal);
