<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\unocha_reliefweb\Services\ReliefWebDocuments;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'reliefweb_document' formatter.
 *
 * @FieldFormatter(
 *   id = "reliefweb_document",
 *   label = @Translation("ReliefWeb Document"),
 *   field_types = {
 *     "reliefweb_document"
 *   }
 * )
 */
class ReliefWebDocument extends FormatterBase {

  /**
   * The ReliefWeb documents service.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebDocuments
   */
  protected $reliefwebDocuments;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ReliefWebDocuments $reliefweb_documents,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );
    $this->reliefwebDocuments = $reliefweb_documents;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('reliefweb.documents')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'white_label' => TRUE,
      'ocha_only' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['white_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('White label URLs'),
      '#default_value' => !empty($this->getSetting('white_label')),
      '#description' => $this->t('If checked, ReliefWeb URLs will be converted to UNOCHA URLs.'),
    ];
    $elements['ocha_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only include OCHA documents'),
      '#default_value' => !empty($this->getSetting('ocha_only')),
      '#description' => $this->t('If checked, only documents from OCHA will be included.'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('White label URLs: @value', [
      '@value' => $this->getSetting('white_label') ? $this->t('Yes') : $this->t('No'),
    ]);
    $summary[] = $this->t('OCHA documents only: @value', [
      '@value' => $this->getSetting('ocha_only') ? $this->t('Yes') : $this->t('No'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $white_label = !empty($this->getSetting('white_label'));

    /** @var \Drupal\unocha_reliefweb\Plugin\Field\FieldType\ReliefWebRiver $item */
    foreach ($items as $delta => $item) {
      $url = $item->getUrl();
      if (empty($url)) {
        continue;
      }

      $river = $item->getRiver();
      if (empty($river)) {
        continue;
      }

      // Use NULL as filter to default to the UN OCHA only filter.
      $filter = !empty($this->getSetting('ocha_only')) ? NULL : [];

      // Get the data for the document.
      $data = $this->getReliefWebDocuments()->getDocumentDataFromUrl($river, $url, $filter, $white_label);
      if (empty($data['entity'])) {
        continue;
      }

      $element = [
        '#theme' => 'unocha_reliefweb_river_article__' . $data['river']['bundle'] . '__' . $this->viewMode,
        '#entity' => $data['entity'],
      ];

      $elements[$delta] = $element;
    }

    return $elements;
  }

  /**
   * Get the ReliefWeb Documents service.
   *
   * @return \Drupal\unocha_reliefweb\Services\ReliefWebDocuments
   *   The ReliefWeb docuements service.
   */
  protected function getReliefWebDocuments() {
    return $this->reliefwebDocuments;
  }

}
