<?php

namespace Drupal\unocha_canto\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'canto_portal' widget.
 *
 * @FieldWidget(
 *   id = "canto_portal",
 *   module = "unocha_canto",
 *   label = @Translation("Canto Portal"),
 *   field_types = {
 *     "canto_portal"
 *   }
 * )
 */
class CantoPortal extends WidgetBase {

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
      '#description' => $this->t('Canto Portal URL.'),
      '#maxlength' => 2048,
    ];

    return $element;
  }

}
