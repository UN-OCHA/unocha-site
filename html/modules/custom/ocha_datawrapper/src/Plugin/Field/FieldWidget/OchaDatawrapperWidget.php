<?php

namespace Drupal\ocha_datawrapper\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ocha_datawrapper\Plugin\Field\FieldFormatter\OchaDatawrapperFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ocha_datawrapper' widget.
 *
 * @FieldWidget(
 *   id = "ocha_datawrapper",
 *   label = @Translation("OCHA datawrapper embed code"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class OchaDatawrapperWidget extends StringTextareaWidget {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $account) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->account = $account;
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
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // Add a callback to validate the embed code.
    $element['#element_validate'][] = [
      get_called_class(),
      'validateEmbedCode',
    ];
    $element['#access'] = $this->account->hasPermission('add datawrapper embed code');
    return $element;
  }

  /**
   * Form element validation handler to validate the embed code.
   *
   * Display an error if the embed code doesn't have the required attributes.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $form
   *   Form.
   */
  public static function validateEmbedCode(array &$element, FormStateInterface $form_state, array $form) {
    if ($element['value']['#value'] !== '') {
      $attributes = OchaDatawrapperFormatter::extractAttributes($element['value']['#value']);
      if (is_null($attributes)) {
        $error = t("Invalid embed code in the @field field. It must be a datawrapper iframe.", [
          '@field' => $element['value']['#title'],
        ]);
        $form_state->setError($element['value'], $error);
      }
      else {
        $missing = OchaDatawrapperFormatter::validateMandatoryAttributes($attributes);
        foreach ($missing as $attribute) {
          $error = t("The iframe in the @field field doesn't have a valid @attribute attribute.", [
            '@field' => $element['value']['#title'],
            '@attribute' => $attribute,
          ]);
          $form_state->setError($element['value'], $error);
        }
      }
    }
  }

}
