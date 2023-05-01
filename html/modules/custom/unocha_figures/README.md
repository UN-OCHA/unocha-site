UNOCHA - Figures module
=======================


This module provides field types, formatters, widgets and services to display Key Figures (ex: funding).

This notably uses the [OCHA Key Figures API](https://keyfigures.api.unocha.org) as source.

## TODO

- [ ] Retrieve country name from taxonomy term corresponding to the ISO3 code.
- [ ] Store if the figure is numeric or not?
- [ ] Widget: improve initial performances by loading data from all existing figures in parallel?
- [ ] Review ODSG and add special handling for OCT as provider until it's added to the OCHA API?
