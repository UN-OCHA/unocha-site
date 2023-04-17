<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'reliefweb_river' formatter.
 *
 * @FieldFormatter(
 *   id = "reliefweb_river",
 *   label = @Translation("ReliefWeb River"),
 *   field_types = {
 *     "reliefweb_river"
 *   }
 * )
 */
class ReliefWebRiver extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'white_label' => TRUE,
      'view_all_link' => FALSE,
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
    $elements['view_all_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show view all link'),
      '#default_value' => !empty($this->getSetting('view_all_link')),
      '#description' => $this->t('If checked, display a link to the ReliefWeb river.'),
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
    $summary[] = $this->t('Show view all link: @value', [
      '@value' => $this->getSetting('view_all_link') ? $this->t('Yes') : $this->t('No'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\unocha_reliefweb\Plugin\Field\FieldType\ReliefWebRiver $item */
    foreach ($items as $delta => $item) {
      $river = $item->getRiver();
      if (empty($river)) {
        continue;
      }

      $data = $item->getRiverData();
      if (empty($data)) {
        continue;
      }

      $title = $item->getTitle();

      $white_label = !empty($this->getSetting('white_label'));

      $element = [
        '#theme' => 'unocha_reliefweb_river',
        '#resource' => $river['resource'],
        '#title' => $title ?: $this->t('List'),
        '#entities' => call_user_func($river['parse'], $river, $data, $white_label),
      ];

      if (!empty($this->getSetting('view_all_link'))) {
        // @todo shall we also white label this link?
        $element['#more']['url'] = $item->getUrl();

        if (!empty($title)) {
          $element['#more']['label'] = $this->t('View all @title', [
            '@title' => $title,
          ]);
        }
      }

      $elements[$delta] = $element;
    }

    return $elements;
  }

}
