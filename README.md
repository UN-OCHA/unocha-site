# UN-OCHA

This is the Drupal 10 codebase of the UN-OCHA corporate site.

## Local development

The [local](local) directory contains a script and setting files that can be
used to quickly set up a local version of the site that will be reachable, by
default, at https://unocha-local.test.

Prevent committing changes to your local files:

Some useful commands:
- `./local/install.sh -m -c -i` will create a new instance from scratch.
- `./local/install.sh -m -d` will recreate the image and containers and install
  the dev dependencies but will preserve the existing database.
- `./local/install.sh -x -v` will remove the containers and file and database
  volumes.

Some useful settings to edit:
- Edit [local/shared/settings/services.yml](local/shared/settings/services.yml)
  to enable debugging of twig and see header cache information for example.
- Edit [local/shared/settings/settings.local.php](local/shared/settings/settings.local.php)
  and uncomment the `$settings['cache']['bins']` lines to disable drupal cache
  and enable/disable the js and css aggregation.

Some workflow tips:
- Install or update packages directly in the container, ex:
  `docker exec -it -w /srv/www unocha-local-site composer require drupal/double_field:^4.1`.
  This installs the module in the container but updates the repository's
  composer files so the changes can be committed.
- Put site specific config in [local/shared/settings/settings.site.php](local/shared/settings/settings.site.php)
- Have git ignore modifications to the [local](local) directory with `git udpate-index --skip-worktree local` (or specific files).
- Disable the above with `git udpate-index --no-skip-worktree local`

