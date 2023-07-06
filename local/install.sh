#!/usr/bin/env bash

set -e -u

# Usage.
usage() {
  echo "Usage: ./local/install.sh [OPTIONS]" >&2
  echo "-h                    : Display usage" >&2
  echo "-m                    : Create local image" >&2
  echo "-i                    : Install site" >&2
  echo "-c                    : Use existing config to install site" >&2
  echo "-d                    : Install dev dependencies" >&2
  echo "-p network            : Indicate the local proxy network name (defaults to 'proxy')" >&2
  echo "-x                    : Shutdown and remove the site containers" >&2
  echo "-v                    : Also remove the volumes when shutting down the containers" >&2
  exit 1
}

create_image="no"
install_site="no"
use_existing_config="no"
install_dev_dependencies="no"
proxy_name="proxy"
shutdown="no"
shutdown_options=""

# Parse options.
while getopts "hmicdp:xv" opt; do
  case $opt in
    h)
      usage
      ;;
    m)
      create_image="yes"
      ;;
    i)
      install_site="yes"
      ;;
    c)
      use_existing_config="yes"
      ;;
    d)
      install_dev_dependencies="yes"
      ;;
    p)
      proxy_name="${OPTARG//,/ }"
      ;;
    x)
      shutdown="yes"
      ;;
    v)
      shutdown_options="$shutdown_options -v"
      ;;
    *)
      usage
      ;;
  esac
done

# Stop and remove the containers.
if [ "$shutdown" = "yes" ]; then
  echo "Stop and remove the containers."
  PROXY=$proxy_name docker compose -p unocha-local -f local/docker-compose.yml down $shutdown_options || true
  exit 0
fi


# Build local image.
if [ "$create_image" = "yes" ]; then
  echo "Build local image."
  make
fi;

# Create the site, memcache and mysql containers.
echo "Create the site, memcache and mysql containers."
PROXY=$proxy_name docker compose -p unocha-local -f local/docker-compose.yml up -d --remove-orphans

# Wait a bit for memcache and mysql to be ready.
echo "Wait a bit for memcache and mysql to be ready."
sleep 10

# Dump some information about the created containers.
echo "Dump some information about the created containers."
docker ps -a -fname=unocha-local

# Install the site.
if [ "$install_site" = "yes" ]; then
  # Ensure the file directories are writable.
  echo "Ensure the file directories are writable."
  docker exec -it unocha-local-site chmod -R 777 /srv/www/html/sites/default/files /srv/www/html/sites/default/private

  # Install the dev dependencies.
  echo "Install the dev dependencies."
  docker exec -it -w /srv/www unocha-local-site composer install

  # Install the subtheme.
  echo "Install the common design subtheme if not present already."
  docker exec -it -w /srv/www unocha-local-site composer run sub-theme

  # Install the site with the existing config.
  if [ "$use_existing_config" = "yes" ]; then
    echo "Install the site with the existing config."
    docker exec -it unocha-local-site drush -y si --existing-config minimal install_configure_form.enable_update_status_emails=NULL
  else
    echo "Install the site from scratch."
    docker exec -it unocha-local-site drush -y si minimal install_configure_form.enable_update_status_emails=NULL
  fi

  # Import the configuration.
  docker exec -it unocha-local-site drush -y cim

# Install the dev dependencies and re-import the configuration.
elif [ "$install_dev_dependencies" = "yes" ]; then
  # Install the dev dependencies.
  echo "Install the dev dependencies."
  docker exec -it -w /srv/www unocha-local-site composer install

  # Import the configuration.
  docker exec -it unocha-local-site drush -y cr

  # Import the configuration.
  docker exec -it unocha-local-site drush -y cim
fi
