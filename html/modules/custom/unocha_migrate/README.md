UNOCHA - Migrate module
=====================

This module handles initial migration/creation of content like the country terms.

It provides the following drush commands:

- `unocha-migrate:update-country-terms`: update or create taxonomy terms from the ReliefWeb API.
- `unocha-migrate:create-redirects`: create redirects (for example from a CSV file).
