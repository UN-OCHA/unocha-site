UNOCHA - Maps module
====================

This module handles map paragraph types.

## Mapbox

The maps are rendered via [mapbox](https://mapbox.com).

## Tile caching

Requests to get the map tiles are proxied and cached via [nginx rules](../../../../docker/etc/nginx/custom/03_mapbox.conf).
