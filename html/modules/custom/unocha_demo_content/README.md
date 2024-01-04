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
# Demo nodes
drush sqlq 'SELECT uuid FROM node WHERE nid = 524'
drush sqlq 'SELECT uuid FROM node WHERE nid = 525'
drush sqlq 'SELECT uuid FROM node WHERE nid = 526'
drush sqlq 'SELECT uuid FROM node WHERE nid = 527'
drush sqlq 'SELECT uuid FROM node WHERE nid = 528'
drush sqlq 'SELECT uuid FROM node WHERE nid = 529'
# Main menu top level
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 2'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 40'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 41'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 42'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 43'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 51'
# Top menu
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 57'
# Footer menu
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 54'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 55'
drush sqlq 'SELECT uuid FROM menu_link_content WHERE id = 56'
```
 See https://www.drupal.org/docs/contributed-modules/default-content-for-d8/defining-default-content


```
To set the homepage, add the UUID to `unocha_demo_content/unocha_demo_content.module`
