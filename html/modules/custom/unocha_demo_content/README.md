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
Or individually:
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
drush sqlq 'SELECT uuid FROM node WHERE nid = 71'
drush sqlq 'SELECT uuid FROM node WHERE nid = 1'
drush sqlq 'SELECT uuid FROM node WHERE nid = 10'
drush sqlq 'SELECT uuid FROM node WHERE nid = 52'
drush sqlq 'SELECT uuid FROM node WHERE nid = 56'
drush sqlq 'SELECT uuid FROM node WHERE nid = 60'
drush sqlq 'SELECT uuid FROM node WHERE nid = 43'

# Main menu top level
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 2'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 40'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 41'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 42'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 43'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 51'

# Main menu second level
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 78'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 1'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 9'
drush sqlq 'SELECT suuid FROM menu_link_content WHERE id = 59'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 63'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 67'
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
Also Media dependencies for nodes listed above, and their `file` dependencies.
And Taxonomy term `taxonomy_term` for Story type.
`drush sqlq "select * from taxonomy_term_data"`

 See https://www.drupal.org/docs/contributed-modules/default-content-for-d8/defining-default-content


```
To set the homepage, add the UUID to `unocha_demo_content/unocha_demo_content.module`
