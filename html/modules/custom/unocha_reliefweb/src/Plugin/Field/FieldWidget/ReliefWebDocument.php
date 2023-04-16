<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'reliefweb_document' widget.
 *
 * @FieldWidget(
 *   id = "reliefweb_document",
 *   module = "unocha_reliefweb",
 *   label = @Translation("ReliefWeb Document"),
 *   field_types = {
 *     "reliefweb_document"
 *   }
 * )
 */
class ReliefWebDocument extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    $default_values = $item->getFieldDefinition()->getDefaultValueLiteral() ?: [];
    $default_values = $default_values[$delta] ?? [];

    $element['url'] = $element + [
      '#type' => 'textfield',
      '#title' => $this->t('Url'),
      '#default_value' => $item->url ?? $default_values['url'] ?? NULL,
      '#description' => $this->t('ReliefWeb Document URL like "https://reliefweb.int/report/country/title".'),
      '#maxlength' => 2048,
    ];

    return $element;
  }

}
