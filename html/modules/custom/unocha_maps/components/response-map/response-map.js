/* global mapboxgl unocha */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.unochaResponseMap = {
    attach: function (context, settings) {
      console.log('unochaResponseMap');

      // Requirements.
      if (!unocha || !unocha.mapbox || !settings || !settings.unochaResponseMap) {
        return;
      }
      console.log(settings.unochaResponseMap);

      // Initialize the RW mapbox handler so we can check support.
      unocha.mapbox.init(settings.unochaResponseMap.mapboxKey, settings.unochaResponseMap.mapboxToken);

      // Skip if the browser doesn't support mapbox GL.
      if (!unocha.mapbox.supported()) {
        // Mark the map as disabled.
        var maps = document.querySelectorAll('[data-response-map]');
        for (var i = 0, l = maps.length; i < l; i++) {
          maps[i].removeAttribute('data-map-enabled');
        }
        return;
      }

      // Redirect to a response's page.
      function redirect(response) {
        var link = response.querySelector('header a');
        if (link) {
          window.location.href = link.getAttribute('href');
        }
      }

      // Set active marker.
      function setActiveMarker(map, marker, active, click) {
        // If the active marker is the same marker and it was clicked, redirect
        // to the response page, otherwise, just skip as it stays active.
        if (active === marker) {
          if (click === true) {
            redirect(marker.response);
          }
        }
        else {
          // Unset the previously active marker.
          unsetActiveMarker(active);

          // Determine if the description should be displayed on the left or
          // right.
          var center = map.getCenter().wrap();
          var lnglat = marker.getLngLat().wrap();
          var position = center.lng > lnglat.lng ? 'right' : 'left';

          // Mark the clicked or hovered marker as being active and show the
          // response description.
          marker.getElement().setAttribute('data-active', '');
          marker.response.setAttribute('data-active', position);
        }
        return marker;
      }

      // Unset active marker.
      function unsetActiveMarker(active) {
        if (active) {
          active.getElement().removeAttribute('data-active');
          active.response.removeAttribute('data-active');
        }
        return null;
      }

      // Extract the coordinates of a response article. A response can cover
      // several countries so can have several pairs of coordinates.
      function extractCoordinates(node) {
        var coordinates = node.getAttribute('data-coordinates').split('|');
        for (var i = 0, l = coordinates.length; i < l; i++) {
          coordinates[i] = coordinates[i].split('x');
        }
        return coordinates;
      }

      // Create a response marker.
      function createMarker(id, coordinates, node) {
        var element = document.createElement('div');
        element.setAttribute('data-id', id);

        if (node.hasAttribute('data-marker-id')) {
          node.setAttribute('data-marker-id', node.getAttribute('data-marker-id') + '|' + id);
        }
        else {
          node.setAttribute('data-marker-id', id);
        }

        var [lon, lat] = coordinates;

        var marker = new mapboxgl.Marker({
          element: element,
          anchor: 'bottom'
        });
        marker.setLngLat({
          // Try to make the coordinate consistent. Note, when using the
          // [-180, 180] range and we have points with a negative longitude and
          // other with a positive one at the extremities of the maps but
          // actually clustered like pacific islands, then the bounds span the
          // entire map instead of the area where the points actually are.
          lon: unocha.mapbox.wrap(lon, -180, 180),
          lat: lat
        });
        marker.id = id;
        marker.response = node;
        marker.responseLink = node.querySelector('a');
        return marker;
      }

      // Find a parent response article from a child element.
      function findParentArticle(container, element) {
        while (element && element !== container) {
          if (element.hasAttribute('data-marker-id')) {
            return element;
          }
          element = element.parentNode;
        }
        return null;
      }

      // Create the map.
      function createMap(element) {
        // Skip if the map was already processed.
        if (element.hasAttribute('data-map-processed')) {
          return;
        }
        // Mark the map has being processed.
        element.setAttribute('data-map-processed', '');

        // Map settings.
        var mapSettings = settings.unochaResponseMap.maps[element.getAttribute('data-response-map')];
        var usePopup = mapSettings.usePopup;

        // Create the container with the legend.
        var container = element.querySelector('[data-map-content]');
        var figure = document.createElement('figure');
        var mapContainer = document.createElement('div');
        figure.className = 'unocha-response-map__map';
        figure.appendChild(mapContainer);
        figure.setAttribute('data-loading', '');
        figure.setAttribute('aria-hidden', true);

        // Create a button to close the popup with the response description.
        if (usePopup) {
          container.setAttribute('data-user-popup', '');

          var button = document.createElement('button');
          button.className = 'unocha-response-map__close';
          button.setAttribute('type', 'button');
          button.appendChild(document.createTextNode(mapSettings.close));
          figure.appendChild(button);
        }

        // Add the map containing figure.
        container.appendChild(figure);

        var markers = {};
        var active = null;
        var bounds = new mapboxgl.LngLatBounds();

        // Create markers for each response. They will be added to the map
        // later. We create them first so we can calculate their bounds and
        // ensure the maps displays all of them.
        var nodes = element.querySelectorAll('article[data-coordinates]');

        for (var i = 0, l = nodes.length; i < l; i++) {
          var node = nodes[i];
          var coordinates = extractCoordinates(node);
          for (var j = 0, m = coordinates.length; j < m; j++) {
            var id = 'marker-' + i + '-' + j;
            var marker = createMarker(id, coordinates[j], node);
            // Extend the bounding box that will be used to set the initial bounds
            // of the response map.
            bounds.extend(marker.getLngLat());
            // Keep track of ther marker so we can display the response
            // description when hovering/clicking on it.
            markers[id] = marker;
          }
        }

        // Map options.
        var options = {
          accessToken: 'token',
          style: 'mapbox://styles/reliefweb/' + unocha.mapbox.key + '?optimize=true',
          container: mapContainer,
          center: [10, 10],
          zoom: 1,
          doubleClickZoom: false,
          minZoom: 1,
          maxZoom: 4
        };

        // Set the initial map zoom and center based on the bounding box of the
        // response locations.
        if (mapSettings.fitBounds === true) {
          options.bounds = bounds;
          options.fitBoundsOptions = {
            // Max zoom is to avoid the map from being unreadable. A zoom of 4
            // allows most of the time to see the affected country entirely when
            // there is for example a single response.
            maxZoom: 4,
            // The padding is to accomodate the zoom controls and mapbox
            // branding.
            padding: 64
          };
          options.minZoom = 0;
        }

        // Replace the mapbox base API with the proxied version.
        mapboxgl.baseApiUrl = window.location.origin + '/mapbox';

        // Create a map.
        var map = new mapboxgl.Map(options)
        // Add the zoom control buttons, bottom left to limit overlap with the
        // response description popup.
        .addControl(new mapboxgl.NavigationControl({
          showCompass: false
        }), 'bottom-left')
        // Add the markers to the map.
        .on('load', function (event) {
          for (var marker in markers) {
            if (markers.hasOwnProperty(marker)) {
              markers[marker].addTo(map);
            }
          }

          // Mark the map as enabled and ready for display.
          element.setAttribute('data-map-enabled', '');
          figure.removeAttribute('data-loading');

          // Set the first marker as active.
          // @todo Instead look for the first response article and extract
          // its set of markers and hilight all of them.
          if (!usePopup) {
            active = setActiveMarker(map, markers['marker-0-0'], null, false);
          }

          // Ensure the map has the proper size because sometimes it will use
          // only half of the height without that.
          map.resize();
        })
        // Restore visibility of the list if the map couldn't be loaded.
        .on('error', function (event) {
          element.removeAttribute('data-map-enabled');
          if (figure && figure.parentNode) {
            figure.parentNode.removeChild(figure);
          }
        })
        // Unset the active marker when clicking on the map.
        .on('click', function (event) {
          var target = event.originalEvent.target;
          // If the article is shown in a popup then hide it when clicking
          // on the map outside of its marker.
          if (usePopup && (!target.hasAttribute || !target.hasAttribute('data-id'))) {
            active = unsetActiveMarker(active);
          }
        });

        // Set a marker as the active one when clicking on it.
        container.addEventListener('click', function (event) {
          var target = event.target;
          if (target.hasAttribute && target.hasAttribute('data-id')) {
            markers[target.getAttribute('data-id')].responseLink.focus();
          }
          // If the article is shown in a popup then hide it when clicking
          // outside of it.
          else if (usePopup) {
            active = unsetActiveMarker(active);
          }
        });

        // Unset the active marker when pressing escape.
        container.addEventListener('keydown', function (event) {
          // If the article is shown in a popup then hide it when pressing ESC.
          if (event.keyCode === 27 && usePopup) {
            active = unsetActiveMarker(active);
          }
        });

        // Set a marker as the active one when focusing its response article.
        container.addEventListener('focusin', function (event) {
          var article = findParentArticle(container, event.target);
          if (article && article.hasAttribute && article.hasAttribute('data-marker-id')) {
            // @todo highlight all the markers for the countries covered by the
            // response.
            active = setActiveMarker(map, markers[article.getAttribute('data-marker-id')], active, false);
          }
          // If the article is shown in a popup then hide it when it's not
          // focused anymore.
          else if (usePopup) {
            active = unsetActiveMarker(active);
          }
        });

        // Disable map zoom when scrolling.
        map.scrollZoom.disable();
      }

      // Create the maps.
      var maps = document.querySelectorAll('[data-response-map]');
      console.log(maps);
      for (var i = 0, l = maps.length; i < l; i++) {
        createMap(maps[i]);
      }
    }
  };
})(Drupal);
