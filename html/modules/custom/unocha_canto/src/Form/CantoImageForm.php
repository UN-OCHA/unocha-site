<?php

namespace Drupal\unocha_canto\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\Form\FileUploadForm;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Form to upload a file or select from the Canto library widget.
 *
 * @internal
 *   Form classes are internal.
 */
class CantoImageForm extends FileUploadForm {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityFormElement(MediaInterface $media, array $form, FormStateInterface $form_state, $delta) {
    $element_form = parent::buildEntityFormElement($media, $form, $form_state, $delta);
    if (!empty($media->id())) {
      $element_form['fields']['#disabled'] = TRUE;
    }
    else {
      // This deserves to be themeable, but it doesn't need to be its own "real"
      // template.
      $element_form['fields']['notice'] = [
        '#type' => 'inline_template',
        '#template' => '<p class="media-unsaved-notice">{{ text }}</p>',
        '#context' => [
          'text' => $this->t('The media item has been created but has not yet been saved. Fill in any required fields and save to add it to the media library.'),
        ],
        '#weight' => -1,
      ];
    }
    return $element_form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    unset($form['description']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) {
    $form = parent::buildInputElement($form, $form_state);

    $form['container']['canto'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Store the JSON data with the Canto assets to insert.
    $form['container']['canto']['assets'] = [
      '#type' => 'hidden',
      '#element_validate' => ['::validateCantoAssets'],
    ];

    // Show a button to display the Canto library.
    $form['container']['canto']['select'] = [
      '#type' => 'button',
      '#value' => $this->t('Select from Canto'),
      '#description' => $this->t('Select a image from the UNOCHA Canto collections'),
      '#attached' => [
        'library' => [
          'unocha_canto/canto.library',
        ],
        'drupalSettings' => [
          'cantoLibrary' => [
            'scheme' => 'image',
            'allowedExtensions' => ['jpg', 'jpeg', 'png'],
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processUploadElement(array $element, FormStateInterface $form_state) {
    $element = parent::processUploadElement($element, $form_state);
    // Ensure the assets value is preserved.
    $element['upload_button']['#limit_validation_errors'] = [
      ['upload'],
      ['current_selection'],
      ['canto'],
      ['canto', 'assets'],
    ];
    // Set an attribute to the submit button so we can easily retrieve it
    // in the Canto library javascript.
    $element['upload_button']['#attributes']['data-canto-submit'] = '';
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Same code as parent except the empty check on `values` to prevent a php
   * error with PHP 8.2+.
   */
  public function validateUploadElement(array $element, FormStateInterface $form_state) {
    if ($form_state::hasAnyErrors()) {
      // When an error occurs during uploading files, remove all files so the
      // user can re-upload the files.
      $element['#value'] = [];
    }
    $values = $form_state->getValue('upload', []);
    if (!empty($values) && count($values['fids']) > $element['#cardinality'] && $element['#cardinality'] !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $form_state->setError($element, $this->t('A maximum of @count files can be uploaded.', [
        '@count' => $element['#cardinality'],
      ]));
      $form_state->setValue('upload', []);
      $element['#value'] = [];
    }
    return $element;
  }

  /**
   * Create the media entities for the selected Canto assets.
   *
   * We do that at the validation step so that we can properly set up form
   * errors. Doing that in the submit callback would be too late.
   *
   * @param array $element
   *   Canto assets form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The form element.
   */
  public function validateCantoAssets(array $element, FormStateInterface $form_state) {
    // Retrieve the JSON encoded list of Canto assets.
    $assets = $this->getCantoAssetData($element, $form_state);
    if (empty($assets)) {
      return $element;
    }

    $media_type = $this->getMediaType($form_state);
    $media_storage = $this->entityTypeManager->getStorage('media');

    // Load existing media.
    $media_entities = $media_storage->loadByProperties([
      'bundle' => $media_type->id(),
      'field_canto_asset_id' => array_keys($assets),
    ]);

    // Remove them from the list so we don't duplicate them.
    foreach ($media_entities as $media) {
      unset($assets[$media->field_canto_asset_id->value]);
    }

    // Create the media entities for the remaining assets.
    if (!empty($assets)) {
      // Create a file item to get the upload location.
      $item = $this->createFileItem($media_type);
      $upload_location = $item->getUploadLocation();

      // Create the destination directory if it doesn't already exists.
      if ($this->fileSystem->prepareDirectory($upload_location, FileSystemInterface::CREATE_DIRECTORY)) {
        // Create the media entities for the remaining assets.
        foreach ($assets as $asset) {
          try {
            $url = strtok($asset['url'], '?');
            // Get the file data from the directUri:
            $data = $this->fetchRemoteFileContent($url);
            if (!empty($data)) {
              // Ensure the filename on disk is unique.
              // @todo maybe prepend the Canto ID to be safe.
              $file = $this->fileRepository->writeData($data, $upload_location . '/' . $asset['name'], FileSystemInterface::EXISTS_RENAME);

              // Create the media for the file and Canto asset.
              $media = $this->createCantoMediaEntity($media_type, $media_storage, $asset, $file);
              $media_entities[] = $media;
            }
            else {
              $this->getLogger('unocha_canto')->error('Unable to download Canto file: ' . $url);
              $form_state->setError($element, $this->t('An error occurred during Canto asset file retrieval.'));
            }
          }
          catch (\Exception $exception) {
            $this->getLogger('unocha_canto')->error($exception->getMessage());
            $form_state->setError($element, $this->t('An error occurred during Canto asset retrieval.'));
          }
        }
      }
      else {
        $this->getLogger('unocha_canto')->error('Unable to created directory: ' . $destination);
        $form_state->setError($element, $this->t('An error occurred during the copy of the Canto assets.'));
      }
    }

    $form_state->setValue(['canto', 'assets'], array_values($media_entities));

    return $element;
  }

  /**
   * Fetch the content of a remote file.
   *
   * @param string $uri
   *   Remote file uri.
   *
   * @return string
   *   File content.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the file's content couldn't be fetch.
   */
  public function fetchRemoteFileContent($uri) {
    try {
      $options = [
        'timeout' => 300,
        'connect_timeout' => 10,
      ];

      // @todo inject client.
      $content = \Drupal::httpClient()
        ->get($uri, $options)
        ->getBody()
        ->getContents();
    }
    catch (RequestException $exception) {
      throw new BadRequestHttpException(strtr('Failed to fetch file due to error "%error"', [
        '%error' => $exception->getMessage(),
      ]));
    }
    if ($content === '') {
      throw new BadRequestHttpException(strtr('The file at @uri was empty', [
        '@uri' => $uri,
      ]));
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadButtonSubmit(array $form, FormStateInterface $form_state) {
    // Media from the images selected from the Canto library.
    $canto_media = $form_state->getValue(['canto', 'assets'], []);
    if (!is_array($canto_media)) {
      $canto_media = [];
    }

    // Files uploaded via the upload file field.
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadMultiple($form_state->getValue('upload', []));

    $this->processInputValues([...$canto_media, ...$files], $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function createMediaFromValue(
    MediaTypeInterface $media_type,
    EntityStorageInterface $media_storage,
    $source_field_name,
    $value,
  ) {
    // Canto assets are already converted to Media in ::validateCantoAssets().
    if ($value instanceof MediaInterface) {
      return $value;
    }

    return parent::createMediaFromValue($media_type, $media_storage, $source_field_name, $value);
  }

  /**
   * Retrieve the Canto asset data from the form input.
   *
   * @param array $element
   *   The Canto asset form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Canto assets keyed by ID.
   */
  protected function getCantoAssetData(array $element, FormStateInterface $form_state) {
    // Retrieve the JSON encoded list of Canto assets.
    $assets_json = $form_state->getValue(['canto', 'assets']);
    if (empty($assets_json)) {
      return [];
    }

    try {
      $assets_data = \json_decode($assets_json, TRUE, 16, JSON_THROW_ON_ERROR);
    }
    catch (\Exception $exception) {
      $form_state->setError($element, $this->t('An error occurred during Canto asset information retrieval.'));
      return [];
    }

    if (empty($assets_data)) {
      return [];
    }

    // Map the assets by their Canto asset ID so we can look up existing ones.
    $assets = [];
    foreach ($assets_data as $asset) {
      $assets[$asset['id']] = $asset;
    }

    return $assets;
  }

  /**
   * Create a media from a Canto asset.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   The media storage.
   * @param array $asset
   *   Asset data from Canto.
   * @param \Drupal\file\FileInterface $file
   *   File entity for the downloaded Canto asset file.
   *
   * @return \Drupal\media\MediaInterface
   *   Media for the Canto Asset.
   */
  protected function createCantoMediaEntity(
    MediaTypeInterface $media_type,
    EntityStorageInterface $media_storage,
    array $asset,
    FileInterface $file,
  ) {
    $source_field_name = $this->getSourceFieldName($media_type);

    // Create the media for the file and add extra information
    // from Canto.
    return $media_storage->create([
      'bundle' => $media_type->id(),
      'name' => $file->getFilename(),
      $source_field_name => [
        'target_id' => $file->id(),
        'alt' => '',
      ],
      'field_canto_asset_id' => $asset['id'],
      'field_image_caption' => $asset['description'] ?? '',
      'field_image_copyright' => $asset['copyright'] ?? '',
    ]);
  }

}
