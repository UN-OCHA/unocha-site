UNOCHA - Utility module
=======================


This module provides various utility helpers, traits and plugins.

## Helpers

This module provides a set of helpers used by other custom modules:

- [DateHelper](src/Helpers/DateHelper.php): get timestamp from a date (string, object etc.) and format date.
- [HtmlOutliner](src/Helpers/HtmlOutliner.php): helper to fix the heading hierarchy of HTML.
- [HtmlSanitizer](src/Helpers/HtmlSanitizer.php): helper to sanitize HTML content.
- [UrlHelper](src/Helpers/UrlHelper.php): helper extending the functionalities of the Drupal UrlHelper.

## Twig extension

This module provides a [twig extension](src/TwigExtension.php) that adds useful filters and functions to help getting or transforming data in the custom templates.
