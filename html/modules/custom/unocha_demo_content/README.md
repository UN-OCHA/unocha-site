# UNOCHA Demo Content

Includes demo content pages, menu items, terms and other supporting entities,
and sets the homepage using the UUID.

To re-create or add default content, add the UUID inside `unocha_demo_content.info.yml`
and run `drush dcem unocha_demo_content`

To get the UUID you can use:

```bash
drush sqlq "select * from node"
drush sqlq "select * from media"
drush sqlq "select * from file_managed"
drush sqlq "select * from taxonomy_term_data"
drush sqlq "select * from block_content"
drush sqlq "select * from menu_link_content"
```

Or if the site has too much content and we only want a sample, we can select
entities individually. The line breaks in the `unocha_demo_content.info.yml`
file represent the groupings below. *This was the best way I could think of to
keep track of what entities I selected for demo content.

The process below is only needed if/when changes to the demo content are made,
and if the demo content is exported again in its entirity. To prevent an entire
re-export, export only the entities that have been changed and update their yml
files individually.
`drush dce [ENITITY] [ID] --folder=modules/custom/unocha_demo_content/content`

Example: `drush dce node 42 --folder=modules/custom/unocha_demo_content/content`

See https://www.drupal.org/docs/contributed-modules/default-content-for-d8/defining-default-content

```bash
# Homepage
drush sqlq 'SELECT uuid FROM node WHERE nid = 42'

# Demo nodes
drush sqlq 'SELECT uuid FROM node WHERE nid = 524'
drush sqlq 'SELECT uuid FROM node WHERE nid = 525'
drush sqlq 'SELECT uuid FROM node WHERE nid = 526'
drush sqlq 'SELECT uuid FROM node WHERE nid = 527'
drush sqlq 'SELECT uuid FROM node WHERE nid = 528'
drush sqlq 'SELECT uuid FROM node WHERE nid = 529'

#Media Centre
drush sqlq 'SELECT uuid FROM node WHERE nid = 50'

# Main menu nodes
# Headquarters
drush sqlq 'SELECT uuid FROM node WHERE nid = 71'

# What we do
drush sqlq 'SELECT uuid FROM node WHERE nid = 52'
drush sqlq 'SELECT uuid FROM node WHERE nid = 51'
drush sqlq 'SELECT uuid FROM node WHERE nid = 53'
drush sqlq 'SELECT uuid FROM node WHERE nid = 54'
drush sqlq 'SELECT uuid FROM node WHERE nid = 55'

# This is OCHA
drush sqlq 'SELECT uuid FROM node WHERE nid = 56'

# Accountability to affected people
drush sqlq 'SELECT uuid FROM node WHERE nid = 60'

# News and stories
drush sqlq 'SELECT uuid FROM node WHERE nid = 43'

# Regions
# ROAP
drush sqlq 'SELECT uuid FROM node WHERE nid = 1'
# ROLAC
drush sqlq 'SELECT uuid FROM node WHERE nid = 2'
# ROMEA
drush sqlq 'SELECT uuid FROM node WHERE nid = 3'
# ROSEA
drush sqlq 'SELECT uuid FROM node WHERE nid = 7'
# ROWCA
drush sqlq 'SELECT uuid FROM node WHERE nid = 8'

# Responses
# Myanmar
drush sqlq 'SELECT uuid FROM node WHERE nid = 10'
# Colombia
drush sqlq 'SELECT uuid FROM node WHERE nid = 40'
# Lebanon
drush sqlq 'SELECT uuid FROM node WHERE nid = 20'
# Burundi
drush sqlq 'SELECT uuid FROM node WHERE nid = 32'
# Burkina Faso
drush sqlq 'SELECT uuid FROM node WHERE nid = 31'

# Selection of Stories
# Story type
drush sqlq 'SELECT uuid FROM node WHERE nid = 668'
drush sqlq 'SELECT uuid FROM node WHERE nid = 739'
# News type
drush sqlq 'SELECT uuid FROM node WHERE nid = 740'
drush sqlq 'SELECT uuid FROM node WHERE nid = 738'
drush sqlq 'SELECT uuid FROM node WHERE nid = 737'
drush sqlq 'SELECT uuid FROM node WHERE nid = 725'
drush sqlq 'SELECT uuid FROM node WHERE nid = 656'
drush sqlq 'SELECT uuid FROM node WHERE nid = 570'
drush sqlq 'SELECT uuid FROM node WHERE nid = 544'
drush sqlq 'SELECT uuid FROM node WHERE nid = 426'
drush sqlq 'SELECT uuid FROM node WHERE nid = 226'
drush sqlq 'SELECT uuid FROM node WHERE nid = 319'
drush sqlq 'SELECT uuid FROM node WHERE nid = 548'
drush sqlq 'SELECT uuid FROM node WHERE nid = 536'
drush sqlq 'SELECT uuid FROM node WHERE nid = 611'

# Main menu top level
# WWW
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 2'
# WWD
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 40'
# WWA
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 41'
# Our Prios
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 42'
# Latest
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 43'
# Donate
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 51'

# Main menu second level
# HQ
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 78'

# ROAP
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 1'
# ROLAC
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 3'
# ROMEA
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 4'
# ROSEA
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 6'
# ROWCA
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 7'

# Responses
# Myanmar
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 9'
# Colombia
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 38'
# Lebanon
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 18'
# Burundi
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 30'
# Burkina Faso
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 99'

# We Advocate
drush sqlq 'SELECT suuid FROM menu_link_content WHERE id = 59'
# This is OCHA
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 63'
# Accountability to affected people
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 67'
# News and stories
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 47'

# Top menu
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 57'

# Footer menu
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 54'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 55'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 56'

# Demo menu items
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 288'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 291'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 293'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 292'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 290'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 289'

# Mega Footer menu items
# WWD
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 161'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 162'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 163'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 165'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 164'

# WWW
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 171'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 176'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 172'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 173'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 174'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 175'

# Latest
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 189'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 191'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 192'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 188'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 295'

# Our priorities
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 177'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 178'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 179'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 180'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 286'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 297'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 187'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 181'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 182'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 183'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 184'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 185'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 186'

# Events
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 167'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 169'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 168'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 166'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 170'

# Take action
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 79'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 80'
```

## Additional Steps
For the Mega Footer menu items, replace the associated nodes with `c615dc92-4e30-4c6e-bc0c-ca24b3d3f088`
as we don't need to create each node to get the menu structure.

Media dependencies for the nodes listed above, and their `file` dependencies,
should be replaced with a generic media entity `044fc54b-448d-4c80-88b4-160d1b03ac46`.

And any video can be replaced with `407820ec-cd73-47f8-b918-c38d281effb1`
`drush sqlq 'SELECT uuid FROM media WHERE id = 52'`

Instead of importing all taxonomy terms, we only use the bare minimum to support
the content model. All Story nodes listed above should have the term entities
replaced with the UUIDs for the Countries below.

```bash
# Taxonomy terms for Story types
# News
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 251'
# Story
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 250'

# Countries
# Myanmar
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 154'
# Colombia
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 53'
# Lebanon
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 126'
# Burundi
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 36'
# Burkina Faso
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 35'

# Office Type
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 260'
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 261'
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 262'
drush sqlq 'SELECT uuid FROM taxonomy_term_data WHERE tid = 263'
```

## Set Homepage
To set the homepage, add the UUID to `unocha_demo_content/unocha_demo_content.module`

## Install the Demo content
This can be used in CI workflows like [run-tests.yml](https://github.com/UN-OCHA/unocha-site/blob/develop/.github/workflows/run-tests.yml) where adding the Github label `e2e` or `performance` runs Jest
or Lighthouse tests, respectively, after importing the demo content.
Or locally for a quick install for testing PRs, and in some cases resetting
shared testing or demo environments to a controlled starting point.

1. Install site from config `drush si --existing-config -y`
2. Enable custom module `drush en unocha_demo_content -y`
