<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'reliefweb_document' field type.
 *
 * @FieldType(
 *   id = "reliefweb_document",
 *   label = @Translation("ReliefWeb Document"),
 *   description = @Translation("A field to display of list a document from ReliefWeb."),
 *   category = @Translation("ReliefWeb"),
 *   default_widget = "reliefweb_document",
 *   default_formatter = "reliefweb_document",
 * )
 */
class ReliefWebDocument extends FieldItemBase {

  /**
   * ReliefWeb API config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The ReliefWeb Documents service.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebDocuments
   */
  protected $reliefwebDocuments;

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'url' => [
          'type' => 'varchar',
          'length' => 2048,
          'not null' => TRUE,
          'sortable' => TRUE,
        ],
      ],
      'indexes' => [
        'url' => ['url'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'url';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['url'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Url'))
      ->setDescription(new TranslatableMarkup('The ReliefWeb Document URL.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'river' => 'updates',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    $options = [];
    foreach ($this->getReliefWebDocuments()->getRivers() as $river => $info) {
      $options[$river] = $info['label'];
    }

    $element['river'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allowed document type'),
      '#description' => $this->t('Select the river to which this document belongs.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('river'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $url = $this->getUrl();
    return empty($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'url' => [
        'Length' => [
          'max' => 2048,
        ],
        'ReliefWebDocumentUrl' => [
          'website' => static::getConfig()->get('website'),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * Get the river URL.
   *
   * @return string
   *   River URL.
   */
  public function getUrl() {
    return $this->get('url')->getValue() ?? '';
  }

  /**
   * Get the river name.
   *
   * @return string
   *   River name.
   */
  public function getRiver() {
    return $this->getSetting('river');
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();

    $values['url'] = 'https://' .
      $random->string(mt_rand(4, 8)) . '.' .
      $random->string(mt_rand(2, 3)) . '/' .
      $random->string(mt_rand(4, 12));

    return $values;
  }

  /**
   * Get the module configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Module configuration.
   *
   * @todo used dependency injection when available for Field items.
   * @see https://www.drupal.org/node/2053415
   */
  protected function getConfig() {
    if (!isset($this->config)) {
      $this->config = \Drupal::config('unocha_reliefweb.settings');
    }
    return $this->config;
  }

  /**
   * Get the ReliefWeb Documents service.
   *
   * @return \Drupal\unocha_reliefweb\Services\ReliefWebDocuments
   *   The ReliefWeb Documents Service.
   *
   * @todo used dependency injection when available for Field items.
   * @see https://www.drupal.org/node/2053415
   */
  protected function getReliefWebDocuments() {
    if (!isset($this->reliefwebDocuments)) {
      $this->reliefwebDocuments = \Drupal::service('reliefweb.documents');
    }
    return $this->reliefwebDocuments;
  }

}
