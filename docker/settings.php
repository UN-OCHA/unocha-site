<?php

// @codingStandardsIgnoreFile
//
/**
 * Add in some extras for our Azure test.
 */
$settings['hash_salt']             = getenv("DRUPAL_HASH_SALT") ?: 'lolsob';
$settings['deployment_identifier'] = getenv("GIT_SHA") ?: \Drupal::VERSION;
$settings['config_sync_directory'] = getenv("DRUPAL_CONFIG_SYNC_DIRECTORY") ?: '/srv/www/config';

/**
 * The UN-OCHA section.
 *
 * Please forget all that has come before.
 *
 * Configure the database for the Drupal via environment variables.
 *
 * Configure everything else via config snippets in a mounted volume on the
 * path /srv/www/shared/settings. This means that this settings.php file can
 * be the same for all properties.
 *
 * The volume should be replaced (eventually) with a secrets store of some sort.
 *
 * Yay!
 */

// Populate the database settings with the environment variables if defined.

$databases['default']['default'] = [
  'driver'    => getenv("AZURE_MYSQL_DRIVER")    ?: getenv("DRUPAL_DATABASE_DRIVER")    ?: 'mysql',
  'username'  => getenv("AZURE_MYSQL_USERNAME")  ?: getenv("DRUPAL_DATABASE_USERNAME")  ?: 'username',
  'password'  => getenv("AZURE_MYSQL_PASSWORD")  ?: getenv("DRUPAL_DATABASE_PASSWORD")  ?: 'password',
  'host'      => getenv("AZURE_MYSQL_HOST")      ?: getenv("DRUPAL_DATABASE_HOST")      ?: 'mysql.example.com',
  'port'      => getenv("AZURE_MYSQL_PORT")      ?: getenv("DRUPAL_DATABASE_PORT")      ?: 3306,
  'database'  => getenv("AZURE_MYSQL_DBNAME")    ?: getenv("DRUPAL_DATABASE_DB")        ?: 'database',
  'prefix'    => getenv("AZURE_MYSQL_PREFIX")    ?: getenv("DRUPAL_DATABASE_PREFIX")    ?: '',
  'charset'   => getenv("AZURE_MYSQL_CHARSET")   ?: getenv("DRUPAL_DATABASE_CHARSET")   ?: 'utf8mb4',
  'collation' => getenv("AZURE_MYSQL_COLLATION") ?: getenv("DRUPAL_DATABASE_COLLATION") ?: 'utf8mb4_general_ci',
];

/**
 * And some more extras for Azure. We have no Ansible-generated settings snippet.
 */
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = array('127.0.0.1', '10.0.0.0/8', $_SERVER['REMOTE_ADDR']);

if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
  $addresses = explode(',', strtr($_SERVER['HTTP_X_FORWARDED_FOR'], array(' ' => '')));

  if (!empty($addresses) && is_array($addresses)) {
    // This should contain the user's actual address, pull it off the
    // array, so it is not added as a known reverse proxy.
    $_my_ip = array_shift($addresses);

    // Add the remaining list of addresses to the list of reverse proxies.
    $settings['reverse_proxy_addresses'] = array_unique(array_merge($settings['reverse_proxy_addresses'], $addresses));
  }
}
$settings['trusted_host_patterns'][] = '^' . preg_quote($_SERVER['HTTP_HOST']) . '$';

$settings['rebuild_access'] = FALSE;
$settings['extension_discovery_scan_tests'] = FALSE;

$settings['file_chmod_directory'] = 02771;
$settings['file_chmod_file'] = 0664;

$config['sanitize_input_logging'] = TRUE;
$settings['make_unused_managed_files_temporary'] = TRUE;

/**
 * Load everything else from snippets under /mnt/drupal/settings.
 *
 * Ideally we turn a lot of this into dynamically loaded config from
 * a key/value store or a keyvault.
 */
if (file_exists('/mnt/drupal/settings')) {
  foreach (glob('/mnt/drupal/settings/settings.*.php') as $filename) {
    include $filename;
  }
}
