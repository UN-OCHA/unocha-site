<?php

namespace Drupal\unocha_figures\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\unocha_figures\Plugin\Field\FieldType\KeyFigure as KeyFigureType;
use Drupal\unocha_figures\Services\OchaKeyFiguresApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'key_figure' widget.
 *
 * @FieldWidget(
 *   id = "key_figure",
 *   label = @Translation("Key Figure"),
 *   field_types = {
 *     "key_figure"
 *   }
 * )
 */
class KeyFigure extends WidgetBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The OCHA Key Figures API client.
   *
   * @var \Drupal\unocha_figures\Services\OchaKeyFiguresApiClient
   */
  protected $ochaKeyFiguresApiClient;

  /**
   * Ajax wrapper ID.
   *
   * @var string
   */
  protected $ajaxWrapperId;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    LoggerChannelFactoryInterface $logger_factory,
    RendererInterface $renderer,
    OchaKeyFiguresApiClient $ocha_key_figure_api_client
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
    );
    $this->logger = $logger_factory->get('unocha_figure_widget');
    $this->renderer = $renderer;
    $this->ochaKeyFiguresApiClient = $ocha_key_figure_api_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('renderer'),
      $container->get('ocha_key_figures_api.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function handlesMultipleValues() {
    return FALSE;
  }

  /**
   * Get the name of the element that triggered the form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $element_parents
   *   The list of parents of the current key figure field element.
   *
   * @return string|null
   *   The name of the subfield of the element that was the trigger if any.
   */
  protected function getTrigger(FormStateInterface $form_state, array $element_parents) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#array_parents'])) {
      $triggering_element_parents = $triggering_element['#array_parents'];
      $triggering_element_name = array_pop($triggering_element_parents);
      if ($triggering_element_parents === $element_parents) {
        return $triggering_element_name;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $field_parents = array_merge($form['#parents'], [$field_name]);
    $element_parents = array_merge($field_parents, ['widget', $delta]);
    $wrapper_id = $this->getAjaxWrapperId($field_parents, $delta);

    $item = $items[$delta];
    $values = $form_state->getValue(array_merge($field_parents, [$delta]));

    // Use the initial item values if there are no form input values.
    if (empty($values)) {
      $values = [
        'provider' => $item->getFigureProvider(),
        'country' => $item->getFigureCountry(),
        'year' => $item->getFigureYear(),
        'id' => $item->getFigureId(),
        'label' => $item->getFigureLabel(),
        'value' => $item->getFigureValue(),
        'unit' => $item->getFigureUnit(),
      ];
    }

    // Unset some values based on the ajax form rebuild triggering.
    $trigger = $this->getTrigger($form_state, $element_parents);
    switch ($trigger) {
      case 'provider':
        unset($values['country'], $values['year'], $values['id'], $values['label'], $values['value'], $values['unit']);
        break;

      case 'country':
        unset($values['year'], $values['id'], $values['label'], $values['value'], $values['unit']);
        break;

      case 'year':
        unset($values['id'], $values['label'], $values['value'], $values['unit']);
        break;

      case 'id':
        unset($values['label'], $values['value'], $values['unit']);
        break;
    }

    // Clear the user input so that the form uses the default values.
    if (!empty($trigger)) {
      NestedArray::unsetValue($form_state->getUserInput(), array_merge($field_parents, [$delta]));
    }

    // Default values.
    $provider = $values['provider'] ?? NULL;
    $country = $values['country'] ?? NULL;
    $year = $values['year'] ?? NULL;
    $figure_id = $values['id'] ?? NULL;
    $label = $values['label'] ?? NULL;
    $value = $values['value'] ?? NULL;
    $unit = $values['unit'] ?? NULL;

    $show_no_data = FALSE;
    $manual = $provider === 'manual';

    // Add the ajax wrapper.
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    // @todo retrieve that from the API client?
    $providers = [
      'manual' => $this->t('Manual'),
    ] + $this->ochaKeyFiguresApiClient->getSupportedProviders();

    $element['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#options' => $providers,
      '#default_value' => $provider,
      '#ajax' => $this->getAjaxSettings($this->t('Loading figure data...'), $field_parents, $delta, $wrapper_id),
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
    ];

    // Extra fields to select the data from a provider.
    if (isset($provider) && !$manual) {
      $label = NULL;
      $value = NULL;

      // Get the list of countries for the provider.
      $countries = $this->getFigureCountries($provider);
      if (empty($countries)) {
        $show_no_data = TRUE;
      }
      else {
        $country = isset($countries[$country]) ? $country : NULL;

        $element['country'] = [
          '#type' => 'select',
          '#title' => $this->t('Country'),
          '#options' => $countries,
          '#default_value' => $country,
          '#ajax' => $this->getAjaxSettings($this->t('Loading figure data...'), $field_parents, $delta, $wrapper_id),
          '#empty_option' => $this->t('- Select -'),
          '#empty_value' => '',
        ];
      }

      // Get the list of years for the provider and country.
      if (!empty($country)) {
        $years = $this->getFigureYears($provider, $country);
        if (empty($years)) {
          $show_no_data = TRUE;
        }
        else {
          $year = isset($years[$year]) ? $year : NULL;

          $element['year'] = [
            '#type' => 'select',
            '#title' => $this->t('Year'),
            '#options' => $years,
            '#default_value' => $year,
            '#ajax' => $this->getAjaxSettings($this->t('Loading figure data...'), $field_parents, $delta, $wrapper_id),
            '#empty_option' => $this->t('- Select -'),
            '#empty_value' => '',
          ];
        }
      }

      // Get the list of figures for the provider, country and year.
      if (!empty($country) && !empty($year)) {
        $figures = $this->getFigures($provider, $country, $year);
        if (empty($figures)) {
          $show_no_data = TRUE;
        }
        else {
          $figure_id = isset($figures[$figure_id]) ? $figure_id : NULL;

          $element['id'] = [
            '#type' => $manual ? 'hidden' : 'select',
            '#title' => $this->t('Id'),
            '#options' => array_map(function ($item) {
              return $item['name'];
            }, $figures),
            '#default_value' => $figure_id,
            '#ajax' => $this->getAjaxSettings($this->t('Loading figure data...'), $field_parents, $delta, $wrapper_id),
            '#empty_option' => $this->t('- Select -'),
            '#empty_value' => '',
          ];

          $label = $figures[$figure_id]['name'] ?? NULL;
          $value = $figures[$figure_id]['value'] ?? NULL;
        }
      }
    }

    if ($show_no_data === TRUE) {
      $element['no_data'] = [
        '#type' => 'item',
        '#markup' => $this->t('No data available. This figure will not be saved.'),
      ];
    }
    else {
      if ($manual || isset($label)) {
        $element['label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#default_value' => $label,
        ];
      }
      if ($manual || isset($value)) {
        $element['value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Value'),
          '#default_value' => $value,
          '#disabled' => !$manual,
        ];

        // @todo maybe only show if the value is numeric?
        $units = KeyFigureType::getSupportedUnits();
        $element['unit'] = [
          '#type' => 'select',
          '#title' => $this->t('Unit'),
          '#options' => $units,
          '#default_value' => isset($units[$unit]) ? $unit : NULL,
          '#empty_option' => $this->t('- None -'),
          '#empty_value' => '',
        ];
      }
    }

    return $element;
  }

  /**
   * Get the contries available for the figure provider.
   *
   * @param string $provider
   *   Provider.
   *
   * @return array
   *   Associative array keyed by country iso3 code and with country names as
   *   values.
   */
  protected function getFigureCountries($provider) {
    if ($provider === 'manual') {
      return [];
    }
    $data = $this->ochaKeyFiguresApiClient->request($provider . '/countries');
    $countries = [];
    if (!empty($data)) {
      foreach ($data as $item) {
        $countries[$item['value']] = $item['label'];
      }
    }
    return $countries;
  }

  /**
   * Get the years available for the figure provider and given country.
   *
   * @param string $provider
   *   Provider.
   * @param string $country
   *   ISO3 code of a country.
   *
   * @return array
   *   Associative array with year as keys and values.
   */
  protected function getFigureYears($provider, $country) {
    if ($provider === 'manual' && !empty($country)) {
      return [];
    }
    $data = $this->ochaKeyFiguresApiClient->request($provider . '/years', [
      'iso3' => $country,
      'order' => [
        'year' => 'desc',
      ],
    ]);
    $years = [];
    if (!empty($data)) {
      foreach ($data as $item) {
        $years[$item['value']] = $item['label'];
      }
    }
    // @todo add a "Latest" year option to instruct to always fetch the most
    // recent figure if available?
    return $years;
  }

  /**
   * Get the figures available for the figure provider, country and year.
   *
   * @param string $provider
   *   Provider.
   * @param string $country
   *   ISO3 code of a country.
   * @param string $year
   *   Year.
   *
   * @return array
   *   Associative array keyed by figure ID and with figures data as values.
   */
  protected function getFigures($provider, $country, $year) {
    if ($provider === 'manual' || empty($country) || empty($year)) {
      return [];
    }
    $data = $this->ochaKeyFiguresApiClient->request($provider, [
      'iso3' => $country,
      'year' => $year,
      'archived' => FALSE,
    ]);
    $figures = [];
    if (!empty($data)) {
      foreach ($data as $item) {
        $figures[$item['id']] = $item;
      }
    }
    return $figures;
  }

  /**
   * Get the base ajax settings for the operation in the widget.
   *
   * @param string $message
   *   The message to display while the request is being performed.
   * @param array $field_parents
   *   The parents of the field.
   * @param int $delta
   *   The delta of the field element.
   * @param string $wrapper_id
   *   The ID of the wrapping HTML element which is going to be replaced.
   *
   * @return array
   *   Array with the ajax settings.
   */
  protected function getAjaxSettings($message, array $field_parents, $delta, $wrapper_id) {
    $path = array_merge($field_parents, ['widget', $delta]);

    return [
      'callback' => [static::class, 'rebuildWidgetForm'],
      'options' => [
        'query' => [
          // This will be used in the ::rebuildWidgetForm() callback to
          // retrieve the widget.
          'element_path' => implode('/', $path),
        ],
      ],
      'wrapper' => $wrapper_id,
      'effect' => 'fade',
      'progress' => [
        'type' => 'throbber',
        'message' => $message,
      ],
      'disable-refocus' => TRUE,
    ];
  }

  /**
   * Get the ajax wrapper id for the field.
   *
   * @param array $field_parents
   *   The parents of the field.
   * @param int $delta
   *   The delta of the field element.
   *
   * @return string
   *   Wrapper ID.
   */
  protected function getAjaxWrapperId(array $field_parents, $delta) {
    return Html::getUniqueId(implode('-', $field_parents) . '-' . $delta . '-ajax-wrapper');
  }

  /**
   * Rebuild form.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax upload.
   */
  public static function rebuildWidgetForm(array &$form, FormStateInterface $form_state, Request $request) {
    // Retrieve the updated widget.
    $parameter = $request->query->get('element_path');
    $path = is_string($parameter) ? explode('/', trim($parameter)) : NULL;

    $response = new AjaxResponse();

    if (isset($path)) {
      // The array parents are populated in the WidgetBase::afterBuild().
      $element = NestedArray::getValue($form, $path);

      if (isset($element)) {
        // Remove the weight field as it's been handled by the tabledrag script
        // and would appear twice otherwise.
        unset($element['_weight']);

        // This will replace the widget with the new one in the form.
        $response->addCommand(new ReplaceCommand(NULL, $element));
      }
    }

    return $response;
  }

}
