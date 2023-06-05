<?php

namespace Drupal\unocha_migrate;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes field purges, adapted from \Drupal\field\ConfigImporterFieldPurger.
 *
 * Note: this is direct copy of https://git.drupalcode.org/project/field_type_converter/-/blob/c06ea72baa146ca539c5bbd477c6ac3af66f7299/src/FieldTypeConverter.php.
 * Thanks to https://www.drupal.org/u/hawkeyetwolf.
 */
final class FieldTypeConverter implements ContainerInjectionInterface {

  /**
   * The schema object for the current database connection.
   *
   * @var \Drupal\Core\Database\Schema
   */
  private $schema;

  /**
   * The Entity Type Manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The key/value collection holding the "installed" entity storage schema.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private $installedStorageSchema;

  /**
   * Constructs a new FieldTypeConverter object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value store factory service.
   */
  private function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    KeyValueFactoryInterface $key_value_factory
  ) {
    $this->schema = $database->schema();
    $this->entityTypeManager = $entity_type_manager;
    $this->installedStorageSchema = $key_value_factory->get('entity.storage_schema.sql');
  }

  /**
   * Creates a new FieldTypeConverter object instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The newly created FieldTypeConverter instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('keyvalue')
    );
  }

  /**
   * Batch processes field type conversions.
   *
   * @param array $sandbox
   *   The batch context sandbox.
   * @param array $original_field_map
   *   The full list of fields to process.
   *   - entity type ID:
   *     - field name: new field type.
   *
   * @return string
   *   Result message for the current batch.
   */
  public static function processBatch(array &$sandbox, array $original_field_map = []): string {
    static::initializeBatch($sandbox, $original_field_map);

    // Have we finished all the fields for the current entity type?
    $entity_type_id = key($sandbox['field_map']);
    $field_name = key($sandbox['field_map'][$entity_type_id]);
    if (!$field_name) {
      // Remove entity type from list and move to next.
      unset($sandbox['field_map'][$entity_type_id]);
      return static::processBatch($sandbox);
    }
    $new_field_type = array_shift($sandbox['field_map'][$entity_type_id]);
    $self = \Drupal::classResolver(static::class);
    $message = $self->convertFieldType($field_name, $new_field_type, $entity_type_id);

    // Update progress and report back.
    $sandbox['progress']++;
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];

    if ($sandbox['#finished'] === 1) {
      drupal_flush_all_caches();
    }

    return "({$sandbox['progress']} / {$sandbox['total']}) " . $message;
  }

  /**
   * Intializes the batch context.
   *
   * @param array $sandbox
   *   The batch context sandbox.
   * @param array $original_field_map
   *   The full list of fields to convert.
   */
  private static function initializeBatch(array &$sandbox, array $original_field_map): void {
    if (isset($sandbox['field_map'])) {
      return;
    }
    $sandbox['field_map'] = $original_field_map;
    $sandbox['progress'] = 0;
    $sandbox['total'] = array_reduce($sandbox['field_map'], function ($count, $field_names) {
      return $count + count($field_names);
    }, 0);
  }

  /**
   * Converts the given field to a new type.
   *
   * @see https://www.drupal.org/docs/drupal-apis/update-api/updating-entities-and-fields-in-drupal-8#s-updating-field-storage-config-items
   *
   * @return string
   *   The status result message.
   */
  public function convertFieldType($field_name, $new_field_type, $entity_type_id) {
    $original_field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);

    // Is there anything to do? I.e., is the field already the target type?
    if ($original_field_storage->getType() === $new_field_type) {
      return "$entity_type_id.$field_name is already field type \"$new_field_type\"";
    }

    // Load entity storage for later use.
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$entity_storage instanceof SqlEntityStorageInterface) {
      $message = "Invalid storage for entity type $entity_type_id.";
      throw new \Exception($message);
    }

    // Create new field storage with updated field type.
    $field_storage_array = $original_field_storage->toArray();
    $field_storage_array['type'] = $new_field_type;
    $field_storage = FieldStorageConfig::create($field_storage_array);

    // Populate info about database tables and columns for this field.
    $table_mapping = $entity_storage->getTableMapping([$field_name => $field_storage]);
    if (!$table_mapping instanceof DefaultTableMapping) {
      $message = "Invalid table mapping for $entity_type_id.$field_name.";
      throw new \Exception($message);
    }
    $table_names = \array_filter(
      $table_mapping->getDedicatedTableNames(),
      function (string $table_name): bool {
        return $this->schema->tableExists($table_name);
      }
    );
    $columns = $table_mapping->getColumnNames($field_name);
    $schemas = array_map(function (string $property) use ($field_storage): array {
      return $field_storage->getSchema()['columns'][$property];
    }, \array_flip($columns));
    $old_columns = $entity_storage
      ->getTableMapping([$field_name => $original_field_storage])
      ->getColumnNames($field_name);

    // Ensure database schema is ready for field changes: install new columns,
    // update existing, and drop removed. Adapted from:
    // @see https://www.drupal.org/project/drupal/issues/937442#comment-13760432
    \array_walk($table_names, function (string $table) use ($columns, $old_columns, $schemas): void {
      // Update existing database columns to match new schema.
      $existing_columns = \array_filter($columns, function (string $column) use ($table): bool {
        return $this->schema->fieldExists($table, $column);
      });
      array_walk(
        array_intersect_key($schemas, \array_flip($existing_columns)),
        function (array $schema, string $column) use ($table): void {
          $this->schema->changeField($table, $column, $column, $schema);
        }
      );
      // Add database columns to match new schema.
      array_walk(
        array_diff_key($schemas, \array_flip($existing_columns)),
        function (array $schema, string $column) use ($table): void {
          $this->schema->addField($table, $column, $schema);
        }
      );
      // Remove database columns that don't exist in the new schema.
      $columns_to_delete = array_diff($old_columns, $columns);
      array_walk($columns_to_delete, function (string $column) use ($table): void {
        $this->schema->dropField($table, $column);
      });
    });

    // Wipe data from the installed storage schema to prevent validation errors
    // when column schema changes are detected.
    $key_name = "$entity_type_id.field_schema_data.$field_name";
    $installed_schema = array_map(function (array $table_schema): array {
        $table_schema['fields'] = [];
        return $table_schema;
    }, $this->installedStorageSchema->get($key_name) ?? []);
    $this->installedStorageSchema->set($key_name, $installed_schema);

    // Now that the database tables and installed schema are prepared, proceed
    // with converting field storage to new type. Intentionally set the new
    // field storage as "original" to allow changing the field type.
    $field_storage->original = $field_storage;
    $field_storage->enforceIsNew(FALSE);
    $field_storage->save();

    // Convert the field instance (per-bundle) configs too.
    $field_instances = $this->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
      ]);
    array_walk($field_instances, function (FieldConfigInterface $original_field_instance) use ($new_field_type): void {
      $field_instance_array = $original_field_instance->toArray();
      $field_instance_array['field_type'] = $new_field_type;
      $new_field_instance = FieldConfig::create($field_instance_array);
      $new_field_instance->enforceIsNew(FALSE);
      $new_field_instance->original = $original_field_instance;
      $new_field_instance->save();
    });
    return "$entity_type_id.$field_name converted to $new_field_type";
  }

}
