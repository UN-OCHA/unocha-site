<?php

/**
 * @file
 * Post update file for the unocha_migrate module.
 */

use Drupal\field_type_converter\FieldTypeConverter;

/**
 * Implements hook_post_update_NAME().
 *
 * Change the Event node's even date field type to handle timezones.
 */
function unocha_migrate_post_update_event_date_timezone(&$sandbox) {
  $field_map['node'] = [
    'field_event_date' => 'daterange_timezone',
  ];
  return FieldTypeConverter::processBatch($sandbox, $field_map);
}
