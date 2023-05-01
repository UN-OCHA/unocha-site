<?php

namespace Drupal\unocha_figures\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drush\Commands\DrushCommands;
use Drupal\unocha_figures\Services\OchaKeyFiguresApiClient;

/**
 * ReliefWeb API Drush commandfile.
 */
class OchaKeyFigureApiCommands extends DrushCommands {

  /**
   * Unocha Figures module config config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $moduleConfig;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The OCHA Key Figures API client.
   *
   * @var \Drupal\unocha_figures\Services\OchaKeyFiguresApiClient
   */
  protected $ochaKeyFiguresApiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Connection $database,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    OchaKeyFiguresApiClient $ocha_key_figure_api_client
  ) {
    $this->moduleConfig = $config_factory->get('unocha_figures.settings');
    $this->database = $database;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->ochaKeyFiguresApiClient = $ocha_key_figure_api_client;
  }

  /**
   * Update OCHA Key Figures.
   *
   * @command unocha-figures:update-ocha-key-figures
   *
   * @usage unocha-figures:update-ocha-key-figures
   *   Update OCHA key figures.
   *
   * @validate-module-enabled unocha_figures
   */
  public function updateOchaKeyFigures() {
    // Get the list of figure fields.
    $field_map = $this->entityFieldManager->getFieldMapByFieldType('key_figure');

    // Supported OCHA Key Figures providers.
    $supported_providers = array_keys($this->ochaKeyFiguresApiClient->getSupportedProviders());

    // Retrieve the data from the key figures fields and store them by provider.
    $providers = [];
    foreach ($field_map as $entity_type_id => $fields) {
      foreach ($fields as $field => $field_info) {
        $query = $this->database->select($entity_type_id . '__' . $field, 'f');
        $query->condition('f.' . $field . '_provider', $supported_providers, 'IN');
        $query->addField('f', 'entity_id', 'entity_id');
        $query->addField('f', 'delta', 'delta');
        $query->addField('f', 'langcode', 'langcode');
        $query->addField('f', $field . '_provider', 'provider');
        $query->addField('f', $field . '_country', 'country');
        $query->addField('f', $field . '_year', 'year');
        $query->addField('f', $field . '_id', 'id');
        $query->addField('f', $field . '_value', 'value');

        $records = $query->execute() ?? [];
        foreach ($records as $record) {
          if (!empty($record->provider)) {
            // We store the record in a nested structure that allows us to
            // reduce the number of API requests and ease the update of the
            // entities with changed figures.
            $providers[$record->provider][$record->country][$record->year][$entity_type_id][$record->entity_id][$record->langcode][$field][$record->delta] = $record;
          }
        }
      }
    }

    // Loop through the list of providers and query the OCHA API for each.
    foreach ($providers as $provider => $countries) {
      $queries = [];
      foreach ($countries as $country => $years) {
        foreach ($years as $year => $entities) {
          $queries[$provider . '.' . $country . '.' . $year] = [
            'entities' => $entities,
            'resource' => $provider,
            'parameters' => [
              'iso3' => $country,
              'year' => $year,
            ],
          ];

        }
      }
      // Perform parallel queries for each set of country/year for the provider.
      $results = $this->ochaKeyFiguresApiClient->requestMultiple($queries);

      // Map the figure values to their ID.
      $value_map = [];
      foreach ($results as $key => $figures) {
        if (!empty($figures)) {
          foreach ($figures as $figure) {
            $value_map[$key][$figure['id']] = $figure['value'];
          }
        }
      }

      // @todo what to do with figures that become "archived"?
      // @todo what to do with figures that aren't in the result set anymore?
      foreach ($queries as $key => $query) {
        if (isset($value_map[$key])) {
          $values = $value_map[$key];
          // Check for changed values and update the corresponding entities.
          foreach ($query['entities'] as $entity_type_id => $entities) {
            $storage = $this->entityTypeManager->getStorage($entity_type_id);

            foreach ($entities as $entity_id => $langcodes) {
              foreach ($langcodes as $langcode => $fields) {
                $entity = NULL;
                foreach ($fields as $field => $records) {
                  foreach ($records as $delta => $record) {
                    // @todo delete the figure if it's not in the list or it's
                    // archived? That may mess up with the design notably and we
                    // don't really have a way to show the change to the
                    // editors. Maybe we can add a "status" field to the Key
                    // Figure and update that, giving a change to Editors to
                    // react and change or remove the figure manually.
                    if (isset($values[$record->id])) {
                      $value = $values[$record->id];

                      // Update the value if it changed.
                      if ($value != $record->value) {
                        // Only load the entity to update if one of its figures
                        // changed.
                        if (!isset($entity)) {
                          $entity = $storage->load($entity_id);
                          // Load the appropriate translation.
                          if ($entity instanceof TranslatableInterface) {
                            $entity = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
                          }
                        }
                        if (isset($entity)) {
                          $entity->{$field}->get($delta)->value = $value;
                        }
                      }
                    }
                  }
                }

                // Update the entity with the updated figure value(s).
                if (isset($entity)) {
                  if ($entity instanceof RevisionableInterface) {
                    $entity->setNewRevision(TRUE);
                  }
                  if ($entity instanceof RevisionLogInterface) {
                    $entity->setRevisionLogMessage('Automatic update of key figures');
                  }
                  $entity->save();
                }
              }
            }
          }
        }
      }
    }
  }

}
