<?php

namespace Drupal\ocha_mediavalet\Plugin\MediaLibrarySource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileRepositoryInterface;
use Drupal\media_library_extend\Plugin\MediaLibrarySource\MediaLibrarySourceBase;
use Drupal\ocha_mediavalet\Services\MediaValetService;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a media library pane to pull placeholder images from lorem.picsum.
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
    $items = $this->queryResults();
    return count($items);
  }

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    $page = $this->getValue('page');
    $count = $this->configuration['items_per_page'];
    $offset = $this->configuration['items_per_page'] * $page;

    $items = $this->queryResults();
    return array_slice($items, $offset, $count, TRUE);
  }

  /**
   * Query the youtube for current results and cache them.
   *
   * @return array
   *   The current set of result data.
   */
  protected function queryResults() {
    if (!$this->getSelectedCategory()) {
      return [];
    }

    $items = $this->mediavaletService->getCategoryAssets($this->getSelectedCategory());

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
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state) {
    $form['query'] = [
      '#title' => $this->t('Search query'),
      '#type' => 'textfield',
    ];

    $form['channel'] = [
      '#title' => $this->t('Select category'),
      '#type' => 'select',
      '#options' => $this->mediavaletService->getCategories(),
      '#description' => $this->t('Select a category from UNOCHA MediaValet'),
      '#default_value' => $this->getSelectedCategory(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Gets the id of the currently selected channel.
   */
  protected function getSelectedCategory() {
    return $this->getValue('channel') ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId($selected_id) {
    $asset = $this->mediavaletService->getAsset($selected_id);

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
