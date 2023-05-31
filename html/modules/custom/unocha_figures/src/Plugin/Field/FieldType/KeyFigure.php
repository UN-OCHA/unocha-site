<?php

namespace Drupal\unocha_figures\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'key_figure' field type.
 *
 * @FieldType(
 *   id = "unocha_figures_key_figure",
 *   label = @Translation("Key Figure"),
 *   description = @Translation("A field to display key figures."),
 *   category = @Translation("UNOCHA"),
 *   default_widget = "key_figure",
 *   default_formatter = "key_figure",
 * )
 */
class KeyFigure extends FieldItemBase {

  /**
   * The OCHA Figures config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'label' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'sortable' => TRUE,
        ],
        'value' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'sortable' => TRUE,
        ],
        'unit' => [
          'type' => 'varchar',
          'length' => 24,
          'not null' => FALSE,
        ],
        'provider' => [
          'type' => 'varchar_ascii',
          'length' => 36,
          'not null' => FALSE,
        ],
        'id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => FALSE,
          'sortable' => FALSE,
        ],
        'country' => [
          'type' => 'varchar_ascii',
          'length' => 4,
          'unsigned' => FALSE,
          'not null' => FALSE,
        ],
        'year' => [
          'type' => 'int',
          'size' => 'normal',
          'unsigned' => FALSE,
          'not null' => FALSE,
        ],
      ],
      'indexes' => [
        'provider' => ['provider'],
        'id' => ['id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'value';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setDescription(new TranslatableMarkup('The figure label.'))
      ->setRequired(TRUE);

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Value'))
      ->setDescription(new TranslatableMarkup('The figure value.'))
      ->setRequired(TRUE);

    $properties['unit'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Unit'))
      ->setDescription(new TranslatableMarkup('Optional unit for the value.'))
      ->setRequired(FALSE);

    $properties['provider'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Provider'))
      ->setDescription(new TranslatableMarkup('Optional provider for the figure (ex: FTS).'))
      ->setRequired(FALSE);

    $properties['id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Id'))
      ->setDescription(new TranslatableMarkup('Optional unique ID for the figures from the provider.'))
      ->setRequired(FALSE);

    $properties['country'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Country'))
      ->setDescription(new TranslatableMarkup('Optional country ISO3 code.'))
      ->setRequired(FALSE);

    $properties['year'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Year'))
      ->setDescription(new TranslatableMarkup('Optional year of the figure.'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->getFigureValue();
    return is_null($value) || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'label' => [
        'Length' => [
          'max' => 255,
        ],
      ],
    ]);
    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Length' => [
          'max' => 255,
        ],
      ],
    ]);

    $constraints[] = $constraint_manager->create('ComplexData', [
      'unit' => [
        'Length' => [
          'max' => 24,
        ],
      ],
    ]);

    $constraints[] = $constraint_manager->create('ComplexData', [
      'provider' => [
        'Length' => [
          'max' => 36,
        ],
      ],
    ]);

    $constraints[] = $constraint_manager->create('ComplexData', [
      'id' => [
        'Length' => [
          'max' => 255,
        ],
      ],
    ]);

    $constraints[] = $constraint_manager->create('ComplexData', [
      'country' => [
        'Length' => [
          'max' => 4,
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * Get the figure label.
   *
   * @return string|null
   *   Figure label.
   */
  public function getFigureLabel() {
    return $this->get('label')->getValue() ?? NULL;
  }

  /**
   * Get the figure value.
   *
   * @return string|null
   *   Figure value.
   */
  public function getFigureValue() {
    return $this->get('value')->getValue() ?? NULL;
  }

  /**
   * Get the figure unit.
   *
   * @return string|null
   *   Figure unit.
   */
  public function getFigureUnit() {
    return $this->get('unit')->getValue() ?? NULL;
  }

  /**
   * Get the figure unit label.
   *
   * @return string|null
   *   Figure unit.
   */
  public function getFigureUnitLabel() {
    $unit = $this->getFigureUnit();
    $units = $this->getSupportedUnits();
    return $units[$unit] ?? NULL;
  }

  /**
   * Get the figure provider.
   *
   * @return string|null
   *   Figure provider.
   */
  public function getFigureProvider() {
    return $this->get('provider')->getValue() ?? NULL;
  }

  /**
   * Get the figure country.
   *
   * @return string|null
   *   Figure country.
   */
  public function getFigureCountry() {
    return $this->get('country')->getValue() ?? NULL;
  }

  /**
   * Get the figure country.
   *
   * @return string|null
   *   Figure country.
   */
  public function getFigureCountryLabel() {
    // @todo do a lookup to retrieve the country name from its ISO3.
    return $this->getFigureCountry();
  }

  /**
   * Get the figure year.
   *
   * @return string|null
   *   Figure year.
   */
  public function getFigureYear() {
    return $this->get('year')->getValue() ?? NULL;
  }

  /**
   * Get the figure id.
   *
   * @return string|null
   *   Figure id.
   */
  public function getFigureId() {
    return $this->get('id')->getValue() ?? NULL;
  }

  /**
   * Get a list of supported units.
   *
   * @return array
   *   Associative array of units.
   */
  public static function getSupportedUnits() {
    return [
      'us$' => t('US$'),
      '%' => t('%'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();

    $values['label'] = substr($random->sentences(mt_rand(2, 5)), 0, 255);

    $values['value'] = mt_rand(1, 50);

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
      $this->config = \Drupal::config('unocha_figures.settings');
    }
    return $this->config;
  }

}
