<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'reliefweb_river' widget.
 *
 * @FieldWidget(
 *   id = "reliefweb_river",
 *   module = "unocha_reliefweb",
 *   label = @Translation("ReliefWeb River"),
 *   field_types = {
 *     "reliefweb_river"
 *   }
 * )
 */
class ReliefWebRiver extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    $default_values = $item->getFieldDefinition()->getDefaultValueLiteral() ?: [];
    $default_values = $default_values[$delta] ?? [];

    $element += [
      '#type' => 'fieldset',
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $item->title ?? $default_values['title'] ?? NULL,
      '#description' => $this->t('River title.'),
      '#maxlength' => 1024,
    ];

    $element['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Url'),
      '#default_value' => $item->url ?? $default_values['url'] ?? NULL,
      '#description' => $this->t('ReliefWeb River URL like "https://reliefweb.int/updates?search=humanitarian".'),
      '#maxlength' => 2048,
    ];

    $element['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#default_value' => $item->limit ?? $default_values['limit'] ?? NULL,
      '#description' => $this->t('Maximum number of documents to request.'),
      '#maxlength' => 3,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // The WidgetBase::extractFormValues() calls filters out empty items by
    // calling FieldItemListInterface::filterEmptyItems(). This prevents adding
    // default values for some sub fields like the title or limit without having
    // to enter a URL as well.
    // So we check if we are on the default value form in which case we run a
    // copy of the WidgetBase::extractFormValues() without the filtering.
    //
    // @see https://www.drupal.org/project/drupal/issues/2708101
    if (!$this->isDefaultValueWidget($form_state)) {
      parent::extractFormValues($items, $form, $form_state);
    }
    else {
      $field_name = $this->fieldDefinition->getName();

      // Extract the values from $form_state->getValues().
      $path = array_merge($form['#parents'], [$field_name]);
      $key_exists = NULL;
      $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

      if ($key_exists) {
        // Account for drag-and-drop reordering if needed.
        if (!$this->handlesMultipleValues()) {
          // Remove the 'value' of the 'add more' button.
          unset($values['add_more']);

          // The original delta, before drag-and-drop reordering, is needed to
          // route errors to the correct form element.
          foreach ($values as $delta => &$value) {
            $value['_original_delta'] = $delta;
          }

          usort($values, function ($a, $b) {
            return SortArray::sortByKeyInt($a, $b, '_weight');
          });
        }

        // Let the widget massage the submitted values.
        $values = $this->massageFormValues($values, $form, $form_state);

        // Assign the values. We do not remove empty items so their values
        // can be used as default when creating new content.
        $items->setValue($values);

        // Put delta mapping in $form_state, so that flagErrors() can use it.
        $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
        foreach ($items as $delta => $item) {
          $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
          unset($item->_original_delta, $item->_weight);
        }
        static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    $error_element = $element;
    foreach ($error->arrayPropertyPath ?? [] as $key) {
      if (isset($error_element[$key])) {
        $error_element = $error_element[$key];
      }
    }
    return $error_element;
  }

}
