<?php

namespace Drupal\unocha_migrate\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use Drupal\unocha_reliefweb\Services\ReliefWebApiClient;
use Drush\Commands\DrushCommands;

/**
 * Unocha Migrate Drush commandfile.
 */
class MigrateCommands extends DrushCommands {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The redirect repository.
   *
   * @var \Drupal\redirect\RedirectRepository
   */
  protected $redirectRepository;

  /**
   * ReliefWeb API Client.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebApiClient
   */
  protected $reliefwebApiClient;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    AliasManagerInterface $path_alias_manager,
    RedirectRepository $redirect_repository,
    ReliefWebApiClient $reliefweb_api_client
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->pathAliasManager = $path_alias_manager;
    $this->redirectRepository = $redirect_repository;
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

  /**
   * Create redirects for CSV file.
   *
   * If neither `file` not `source/target` is provided then it tries to read
   * from stdin.
   *
   * @param array $options
   *   Options for the command.
   *
   * @command unocha-migrate:create-redirects
   *
   * @option file Path to a CSV file containing pairs of source -> target redirect URLs.
   * @option source Source URL of redirect. It must be used in conjunction with the target option.
   * @option target Source URL of redirect. It must be used in conjunction with the source option.
   * @option language Language for the redirect. Default to English.
   *
   * @default $options []
   *
   * @usage unocha-migrate:create-redirects --file=/tmp/test.csv
   *   Create redirects from the test.csv file.
   * @usage unocha-migrate:create-redirects --source=/truc --target=/bidule
   *   Create a redirect from /truc to /bidule.
   * @usage unocha-migrate:create-redirects < ./test.csv
   *   Create redirects from CSV data was passed to stdin.
   *
   * @validate-module-enabled unocha_migrate
   */
  public function createRedirects($options = [
    'file' => NULL,
    'source' => NULL,
    'target' => NULL,
    'language' => 'en',
  ]) {
    $file = $options['file'] ?? NULL;
    $source = $options['source'] ?? NULL;
    $target = $options['target'] ?? NULL;
    $language = $options['language'] ?? 'en';

    if (empty($source) && empty($target)) {
      // Ensure the auto detect line endings in on so that it can work with
      // windows or unix files. This needs to happen before opening a file.
      $auto_detect_line_endings = ini_get('auto_detect_line_endings') ?? FALSE;
      ini_set('auto_detect_line_endings', TRUE);

      // Read from a CSV file.
      if (!empty($file)) {
        if (!file_exists($file)) {
          $this->logger()->error("The file '$file' doesn't exist");
          return FALSE;
        }
        $handle = fopen($file, 'r');
      }
      // Otherwise try to read from the standard input.
      else {
        $file = 'stdin';
        $handle = \STDIN;
        // Make sure we do not block on the standard input which happens when
        // it's empty.
        stream_set_blocking($handle, FALSE);
      }

      if ($handle === FALSE) {
        $this->logger()->error("Unable to open the file '$file'");
        return FALSE;
      }
      try {
        $count = 0;
        $valid = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
          $count++;
          if (count($data) >= 2 && $this->createRedirect($data[0], $data[1], $language)) {
            $valid++;
          }
        }
      }
      catch (\Exception $exception) {
        $this->logger()->error($exception->getMessage());
      }
      fclose($handle);

      // Reset the line endings detection.
      ini_set('auto_detect_line_endings', $auto_detect_line_endings);

      if ($count === 0) {
        $this->logger()->warning('No data found');
      }
      else {
        $this->logger()->info("Processed $valid/$count valid redirects.");
      }
    }
    elseif (empty($source)) {
      $this->logger()->error('Missing source URL');
      return FALSE;
    }
    elseif (empty($target)) {
      $this->logger()->error('Missing target URL');
      return FALSE;
    }
    else {
      if ($this->createRedirect($source, $target, $language)) {
        $this->logger()->info("Processed '$source' redirect.");
      }
      else {
        $this->logger()->warning("Invalid source '$source' or target '$target'");
      }
    }
  }

  /**
   * Create a redirect for the source and target.
   *
   * @param string $source
   *   Source URL or path.
   * @param string $target
   *   Target URL or path.
   * @param string $language
   *   Language code.
   *
   * @return bool
   *   TRUE if the redirect was created or already existed.
   */
  public function createRedirect($source, $target, $language = 'en') {
    $source_path = parse_url(preg_replace('#^(https?://[^/]+)?/+#', 'https://wwww.unocha.org/', $source), \PHP_URL_PATH);
    $target_path = parse_url(preg_replace('#^(https?://[^/]+)?/+#', 'https://wwww.unocha.org/', $target), \PHP_URL_PATH);

    if (empty($source_path)) {
      $this->logger()->warning("Invalid source: '$source'");
      return FALSE;
    }
    if (empty($target_path)) {
      $this->logger()->warning("Invalid target: '$target'");
      return FALSE;
    }

    // Prevent double encoding.
    $source_path = rawurldecode($source_path);
    $target_path = rawurldecode($target_path);

    try {
      $target_path = $this->pathAliasManager->getPathByAlias($target_path, $language) ??
        $this->redirectRepository->findMatchingRedirect($target_path, [], $language)?->getRedirect() ??
        $target_path;

      $redirect = $this->redirectRepository->findMatchingRedirect($source_path, [], $language) ??
        Redirect::create();

      $redirect->setSource($source_path);
      $redirect->setRedirect($target_path);
      $redirect->setLanguage($language);
      $redirect->setStatusCode($this->configFactory->get('redirect.settings')->get('default_status_code'));
      $result = $redirect->save();

      if ($result === SAVED_NEW) {
        $this->logger()->info("Created redirect from '$source' to '$target'");
      }
      else {
        $this->logger()->info("Updated redirect from '$source' to '$target'");
      }
    }
    catch (\Exception $exception) {
      $this->logger()->warning("Unable to create redirect for '$source': $exception->getMessage()");
      return FALSE;
    }

    return TRUE;
  }

}
