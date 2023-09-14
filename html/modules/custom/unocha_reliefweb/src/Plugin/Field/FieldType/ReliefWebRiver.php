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
 * Plugin implementation of the 'reliefweb_river' field type.
 *
 * @FieldType(
 *   id = "reliefweb_river",
 *   label = @Translation("ReliefWeb River"),
 *   description = @Translation("A field to display of list of documents from ReliefWeb."),
 *   category = @Translation("ReliefWeb"),
 *   default_widget = "reliefweb_river",
 *   default_formatter = "reliefweb_river",
 * )
 */
class ReliefWebRiver extends FieldItemBase {

  /**
   * Maximum number of documents that can be requested.
   */
  const LIMIT = 100;

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
        'title' => [
          'type' => 'varchar',
          'length' => 1024,
          'not null' => TRUE,
          'sortable' => TRUE,
        ],
        'limit' => [
          'type' => 'int',
          'description' => 'Maximum number of documents to request.',
          'not null' => TRUE,
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
      ->setDescription(new TranslatableMarkup('The ReliefWeb River URL.'))
      ->setRequired(TRUE);

    $properties['title'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setDescription(new TranslatableMarkup('Optional title for the river.'))
      ->setRequired(TRUE);

    $properties['limit'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Limit'))
      ->setDescription(new TranslatableMarkup('Maximum number of documents to request.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'rivers' => ['updates'],
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

    $element['rivers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed rivers'),
      '#description' => $this->t('Select the rivers that can be linked in this field.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('rivers'),
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
        'ReliefWebRiverUrl' => [
          'rivers' => $this->getSetting('rivers'),
          'website' => static::getConfig()->get('website'),
        ],
      ],
    ]);
    $constraints[] = $constraint_manager->create('ComplexData', [
      'title' => [
        'Length' => [
          'max' => 1024,
        ],
      ],
    ]);
    $constraints[] = $constraint_manager->create('ComplexData', [
      'limit' => [
        'Range' => [
          'min' => 1,
          'max' => self::LIMIT,
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
   * Get the river title.
   *
   * @return string
   *   River title.
   */
  public function getTitle() {
    return $this->get('title')->getValue() ?? '';
  }

  /**
   * Get the maximum number of items to retrieve from the API.
   *
   * @return int
   *   Limit.
   */
  public function getLimit() {
    return $this->get('limit')->getValue();
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

    $values['title'] = $random->sentences(mt_rand(2, 5));

    $values['limit'] = mt_rand(1, 50);

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
