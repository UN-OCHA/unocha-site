<?php

namespace Drupal\ocha_mediavalet\Plugin\MediaLibrarySource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileRepositoryInterface;
use Drupal\media_library_extend\Plugin\MediaLibrarySource\MediaLibrarySourceBase;
use Drupal\ocha_mediavalet\Api\MediaValetClient;
use Drupal\ocha_mediavalet\Api\MediaValetData;
use Drupal\ocha_mediavalet\Services\MediaValetService;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a media library pane to pull images from MediaValet.
 *
 * @MediaLibrarySource(
 *   id = "ocha_mediavalet_image",
 *   label = @Translation("MediaValet"),
 *   source_types = {
 *     "image"
 *   },
 * )
 */
class MediaValetImage extends MediaLibrarySourceBase {

  /**
   * The target bundle for this plugin.
   *
   * @var string
   */
  protected $targetBundle = 'image';

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * MediaValet service.
   *
   * @var \Drupal\ocha_mediavalet\Services\MediaValetService
   */
  protected $mediavaletService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('token'),
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('file.repository'),
      $container->get('ocha_mediavalet.service.client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Token $token, FileSystemInterface $file_system, Client $http_client, FileRepositoryInterface $file_repository, MediaValetService $mediavalet_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $token, $file_system);
    $this->httpClient = $http_client;
    $this->fileRepository = $file_repository;
    $this->mediavaletService = $mediavalet_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getCount() {
    $data = $this->queryResults();
    $result_info = $data->getResultInfo();
    return $result_info['total'] ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    static $results = [];
    if (!empty($results)) {
      return $results;
    }

    $data = $this->queryResults();
    $items = $data->getData();
    $results = [];

    foreach ($items as $item) {
      $results[] = [
        'id' => $item['id'],
        'label' => $item['title'],
        'preview' => [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $item['thumb'],
            'alt' => $item['title'],
            'title' => $item['title'],
          ],
        ],
      ];
    }

    return $results;
  }

  /**
   * Query media valet current results and cache them.
   */
  protected function queryResults() : MediaValetData {
    if (!$this->getSelectedCategory() && !$this->getSearch()) {
      return new MediaValetData([], []);
    }

    $this->mediavaletService
      ->setMediaType(MediaValetClient::MEDIATYPEIMAGE)
      ->setCount($this->configuration['items_per_page'])
      ->setOffset($this->getValue('page') * $this->configuration['items_per_page']);

    return $this->mediavaletService->search($this->getSearch(), $this->getSelectedCategory());
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state) {
    $form['query'] = [
      '#title' => $this->t('Search query'),
      '#type' => 'textfield',
      '#description' => $this->t('Free text search, searches names, keywords and categories.'),
    ];

    $form['category'] = [
      '#title' => $this->t('Select category'),
      '#type' => 'select_a11y',
      '#cardinality' => 1,
      '#select_a11y' => [
        'width' => 'element',
      ],
      '#options' => $this->getCategories(),
      '#description' => $this->t('Select a category from UNOCHA MediaValet'),
      '#default_value' => $this->getSelectedCategory(),
      '#empty_option' => $this->t('- Select a category -'),
      '#empty_value' => '',
    ];

    return $form;
  }

  /**
   * Get categories.
   */
  protected function getCategories() {
    $data = $this->mediavaletService
      ->setMediaType(MediaValetClient::MEDIATYPEIMAGE)
      ->getCategories();

    return $data->getData();
  }

  /**
   * Gets the id of the currently selected category.
   */
  protected function getSelectedCategory() {
    return $this->getValue('category') ?? NULL;
  }

  /**
   * Gets the search string.
   */
  protected function getSearch() {
    return $this->getValue('query') ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId($selected_id) {
    $data = $this->mediavaletService->getAsset($selected_id);
    $asset = $data->getData();

    // Create a media entity.
    $entity = $this->createEntityStub($asset['title']);
    $image = file_get_contents($asset['original']);
    $filename = $asset['filename'];

    // Save to filesystem.
    $file = $this->fileRepository->writeData($image, $this->getUploadLocation() . '/' . $filename);

    // Attach file to media entity.
    $source_field = $this->getSourceField();
    $entity->{$source_field}->target_id = $file->id();
    $entity->{$source_field}->alt = $asset['title'];
    $entity->save();

    return $entity->id();
  }

}
