UNOCHA - ReliefWeb module
=========================

This module provides integration with ReliefWeb.

## Integrations

- [ReliefWeb API Client](src/Services/ReliefWebApiClient.php): service (`reliefweb_api.client`) to retrieve data from the ReliefWeb API
- [ReliefWeb API Converter](src/Services/ReliefWebApiConverter.php): service (`reliefweb_api.converter`) to call the river to API payload conversion endpoint on ReliefWeb
- [ReliefWeb Document Controller](src/Controller/ReliefWebDocument.php): controller to display RW documents as if unocha.org pages
- [ReliefWeb River Field Type](src/Plugin/Field/FieldType/ReliefWebRiver.php): field type with widget and formatter to display a list of ReliefWeb documents from a ReliefWeb "river" URL (ex: https://reliefweb.int/updates)

## Notes

A large part of the code was copied from the following modules of the ReliefWeb site codebase:

- https://github.com/UN-OCHA/rwint9-site/tree/develop/html/modules/custom/reliefweb_api
- https://github.com/UN-OCHA/rwint9-site/tree/develop/html/modules/custom/reliefweb_entities
- https://github.com/UN-OCHA/rwint9-site/tree/develop/html/modules/custom/reliefweb_rivers
