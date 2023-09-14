<?php

namespace Drupal\unocha_canto\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library\Form\OEmbedForm;

/**
 * Form to upload a file or select from the Canto library widget.
 *
 * @internal
 *   Form classes are internal.
 */
class CantoVideoForm extends OEmbedForm {

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) {
    $form = parent::buildInputElement($form, $form_state);

    // Set an attribute to the submit button so we can easily retrieve it
    // in the Canto library javascript.
    $form['container']['submit']['#attributes']['data-canto-submit'] = '';

    $form['container']['canto'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Show a button to display the Canto library.
    $form['container']['canto']['select'] = [
      '#type' => 'button',
      '#value' => $this->t('Select from Canto'),
      '#description' => $this->t('Select a video from the UNOCHA Canto collections'),
      '#attached' => [
        'library' => [
          'unocha_canto/canto.library',
        ],
        'drupalSettings' => [
          'cantoLibrary' => [
            'scheme' => 'video',
            'allowedExtensions' => [],
          ],
        ],
      ],
    ];

    return $form;
  }

}
