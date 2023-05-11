<?php

namespace Drupal\unocha_canto\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'canto_portal' field type.
 *
 * @FieldType(
 *   id = "canto_portal",
 *   label = @Translation("Canto Portal"),
 *   description = @Translation("A field to display Canto portals."),
 *   category = @Translation("UNOCHA"),
 *   default_widget = "canto_portal",
 *   default_formatter = "canto_portal",
 * )
 */
class CantoPortal extends FieldItemBase {

  /**
   * Canto config.
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
      ->setDescription(new TranslatableMarkup('The Canto Portal URL.'))
      ->setRequired(TRUE);

    return $properties;
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
        'CantoPortalUrl' => [
          'website' => static::getConfig()->get('canto_site_base_url'),
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
      $this->config = \Drupal::config('unocha_canto.settings');
    }
    return $this->config;
  }

}
