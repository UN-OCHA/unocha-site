<?php

namespace Drupal\unocha_reliefweb\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the ReliefWeb breadcrumbs.
 */
class BreadcrumbSettingsForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_path = $this->state->get('unocha_reliefweb.breadcrumb.default_path', '');

    $form['default_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default path'),
      '#description' => $this->t('Indicate the default parent path for the white labeled ReliefWeb documents. Ex: /publications.'),
      '#default_value' => $default_path,
    ];

    $paths = $this->state->get('unocha_reliefweb.breadcrumb.ocha_product_paths', []);
    $default = [];
    foreach ($paths as $key => $info) {
      $default[$key] = $info['ocha_product'] . ':' . $info['path'];
    }

    $form['ocha_product_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('OCHA product path mapping'),
      '#description' => $this->t('Indicate <em>one per line</em> the parent path of the white labeled ReliefWeb documents with a particular OCHA product, in the form: ocha_product:/path. Ex: Press Release:/latest/press-releases.'),
      '#default_value' => implode("\n", $default),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];
    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (preg_match_all('#^\s*(?<ocha_product>[^:]+)\s*:\s*(?<path>\S+)\s*$#m', $form_state->getValue('ocha_product_paths') ?? '', $matches, \PREG_SET_ORDER) !== FALSE) {
      $paths = [];
      foreach ($matches as $match) {
        if (!empty($match['ocha_product']) && !empty($match['path'])) {
          $paths[mb_strtolower($match['ocha_product'])] = [
            'ocha_product' => $match['ocha_product'],
            'path' => '/' . trim($match['path'], '/'),
          ];
        }
      }
      $this->state->set('unocha_reliefweb.breadcrumb.ocha_product_paths', $paths);
    }

    $default_path = '/' . trim($form_state->getValue('default_path') ?? '', '/');
    $this->state->set('unocha_reliefweb.breadcrumb.default_path', $default_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unocha_reliefweb_breadcrumb_settings';
  }

}
