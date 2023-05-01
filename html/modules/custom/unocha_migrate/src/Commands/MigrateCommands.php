<?php

namespace Drupal\unocha_migrate\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\unocha_reliefweb\Services\ReliefWebApiClient;
use Drush\Commands\DrushCommands;

/**
 * Unocha Migrate Drush commandfile.
 */
class MigrateCommands extends DrushCommands {

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ReliefWeb API Client.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebApiClient
   */
  protected $reliefwebApiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ReliefWebApiClient $reliefweb_api_client
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->reliefwebApiClient = $reliefweb_api_client;
  }

  /**
   * Update/create country taxonomy terms from ReliefWeb API countries.
   *
   * @command unocha-migrate:update-country-terms
   *
   * @usage unocha-migrate:update-country-terms
   *   Update/create country terms.
   *
   * @validate-module-enabled unocha_migrate
   */
  public function updateCountryTerms() {
    // Check if the taxonomy entity types exist.
    if (!$this->entityTypeManager->hasDefinition('taxonomy_vocabulary')) {
      $this->logger()->error('Missing taxonomy_vocabulary entity type.');
      return;
    }
    if (!$this->entityTypeManager->hasDefinition('taxonomy_term')) {
      $this->logger()->error('Missing taxonomy_term entity type.');
      return;
    }

    // Check if the country vocabulary exists.
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('country');
    if (empty($vocabulary)) {
      $this->logger()->error('Missing country vocabulary.');
      return;
    }

    // Taxonomy term storage.
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Retrieve the country data from the ReliefWeb API.
    $api_data = $this->reliefwebApiClient->request('countries', [
      'limit' => 1000,
      'fields' => [
        'include' => ['*'],
        'exclude' => [
          'profile',
          'description',
          'description-html',
        ],
      ],
      'sort' => ['name.collation_en:asc'],
    ]);

    if (empty($api_data['data'])) {
      $this->logger()->error('Unable to retrieve ReliefWeb country data.');
      return;
    }

    // Load the existing country terms and store them for easy lookup.
    $existing_terms = [];
    foreach ($storage->loadByProperties(['vid' => 'country']) as $term) {
      $existing_terms[$term->name->value] = $term;
      if (!empty($term->field_iso3->value)) {
        $existing_terms[$term->field_iso3->value] = $term;
      }
      if (!empty($term->field_shortname->value)) {
        $existing_terms[$term->field_shortname->value] = $term;
      }
    }

    // Create or update the country terms.
    $updated = 0;
    foreach ($api_data['data'] as $item) {
      $fields = $item['fields'];

      $term = $existing_terms[$fields['iso3']] ??
              $existing_terms[$fields['name']] ??
              $existing_terms[$fields['shortname']] ??
              $storage->create(['vid' => 'country']);

      $update = $term->name->value !== $fields['name'] ||
                $term->field_iso3->value !== $fields['iso3'] ||
                $term->field_shortname->value !== $fields['shortname'];

      $location = '';
      // World doesn't have centroid coordinates.
      if ($fields['name'] !== 'World') {
        $location = 'POINT (' . $fields['location']['lon'] . ' ' . $fields['location']['lat'] . ')';
        $update = $update || $term->field_location->value !== $location;
      }

      if ($update) {
        $updated++;
        $new = $term->isNew();

        $term->name->value = $fields['name'];
        $term->field_iso3->value = $fields['iso3'];
        $term->field_shortname->value = $fields['shortname'];
        $term->field_location->value = $location;
        $term->save();

        if ($new) {
          $this->logger()->info('Created ' . $fields['name']);
        }
        else {
          $this->logger()->info('Updated ' . $fields['name']);
        }
      }
    }

    $this->logger()->info('Updated/created ' . $updated . ' countries');
  }

}
