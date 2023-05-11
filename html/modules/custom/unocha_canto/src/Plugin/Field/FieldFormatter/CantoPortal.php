<?php

namespace Drupal\unocha_canto\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'canto_portal' formatter.
 *
 * @FieldFormatter(
 *   id = "canto_portal",
 *   label = @Translation("Canto Portal"),
 *   field_types = {
 *     "canto_portal"
 *   }
 * )
 */
class CantoPortal extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'width' => NULL,
      'height' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content width'),
      '#default_value' => $this->getSetting('width'),
    ];
    $elements['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content height'),
      '#default_value' => $this->getSetting('height'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $width = $this->getSetting('width');
    if (isset($width)) {
      $summary[] = $this->t('Width: @value', [
        '@value' => $width,
      ]);
    }
    $height = $this->getSetting('height');
    if (isset($height)) {
      $summary[] = $this->t('Height: @value', [
        '@value' => $height,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\unocha_canto\Plugin\Field\FieldType\CantoPortal $item */
    foreach ($items as $delta => $item) {
      $url = $item->getUrl();
      if (isset($url)) {
        $elements[$delta] = [
          '#theme' => 'unocha_canto_portal__' . $this->viewMode,
          '#url' => $url,
        ];
      }
    }

    return $elements;
  }

}
