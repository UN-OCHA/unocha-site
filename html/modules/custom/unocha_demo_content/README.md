# UNOCHA Demo Content

Includes demo content pages, and set the homepage.

To re-create or add default content the easiest is to add the uuid inside `unocha_demo_content.info.yml` and run `drush dcem unocha_demo_content`

To get the uuid you can use:

```bash
drush sqlq "select * from node"
drush sqlq "select * from media"
drush sqlq "select * from file_managed"
drush sqlq "select * from taxonomy_term_data"
drush sqlq "select * from block_content"
drush sqlq "select * from menu_link_content"
```
Or when the site has too much content and we only want a sample, we can select
entities individually. The line breaks in the `unocha_demo_content.info.yml`
file represent the groupings below. This was the best way I could think of to
keep track of what entities I selected for demo content.
```
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
```
Also Media dependencies for nodes listed above, and their `file` dependencies can be replaces with a generic media entity `044fc54b-448d-4c80-88b4-160d1b03ac46`.

And the video `407820ec-cd73-47f8-b918-c38d281effb1`
drush sqlq 'SELECT uuid FROM media WHERE id = 52'

Taxonomy term `taxonomy_term` for Story types.
`drush sqlq "select * from taxonomy_term_data"`

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

 See https://www.drupal.org/docs/contributed-modules/default-content-for-d8/defining-default-content


```
To set the homepage, add the UUID to `unocha_demo_content/unocha_demo_content.module`
