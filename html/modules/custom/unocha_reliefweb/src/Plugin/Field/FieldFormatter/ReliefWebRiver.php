<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\Pager;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\unocha_reliefweb\Services\ReliefWebDocuments;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The pager manager servie.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

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
    PagerManagerInterface $pager_manager
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
    $this->pagerManager = $pager_manager;
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
      $container->get('reliefweb.documents'),
      $container->get('pager.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'white_label' => TRUE,
      'ocha_only' => TRUE,
      'view_all_link' => FALSE,
      'paginated' => FALSE,
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
    $elements['view_all_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show view all link'),
      '#default_value' => !empty($this->getSetting('view_all_link')),
      '#description' => $this->t('If checked, display a link to the ReliefWeb river.'),
    ];
    $elements['paginated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Paginated'),
      '#default_value' => !empty($this->getSetting('paginated')),
      '#description' => $this->t('If checked, show a pager to navigate the list.'),
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
    $summary[] = $this->t('Show view all link: @value', [
      '@value' => $this->getSetting('view_all_link') ? $this->t('Yes') : $this->t('No'),
    ]);
    $summary[] = $this->t('Paginated: @value', [
      '@value' => $this->getSetting('paginated') ? $this->t('Yes') : $this->t('No'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $white_label = !empty($this->getSetting('white_label'));
    $paginated = !empty($this->getSetting('paginated'));

    /** @var \Drupal\unocha_reliefweb\Plugin\Field\FieldType\ReliefWebRiver $item */
    foreach ($items as $delta => $item) {
      $url = $item->getUrl();
      if (empty($url)) {
        continue;
      }

      $limit = $item->getLimit();
      if (empty($limit)) {
        continue;
      }

      $pager_id = NULL;
      if ($paginated) {
        $pager_id = $this->pagerManager->getMaxPagerElementId() + 1;
      }

      $offset = isset($pager_id) ? $this->pagerManager->findPage($pager_id) : 0;

      $data = $this->getReliefWebDocuments()->getRiverDataFromUrl($url, $limit, $offset, NULL, $white_label);
      if (empty($data['entities'])) {
        continue;
      }

      $element = [
        '#theme' => 'unocha_reliefweb_river__' . $this->viewMode,
        '#resource' => $data['river']['resource'],
        '#title' => $item->getTitle() ?: '',
        '#entities' => $data['entities'],
      ];

      if (!empty($this->getSetting('view_all_link'))) {
        // @todo shall we also white label this link and/or convert it to
        // a URL on the /publications page?
        $element['#more']['url'] = $item->getUrl();

        if (!empty($title)) {
          $element['#more']['label'] = $this->t('View all @title', [
            '@title' => $title,
          ]);
        }
      }

      // Initialize the pager.
      if (isset($pager_id) && !empty($data['total'])) {
        $pager = $this->pagerManager->createPager($data['total'], $limit, $pager_id);

        // Add the pager.
        $element['#pager'] = [
          '#type' => 'pager',
          '#element' => $pager_id,
        ];

        // Add the results.
        $element['#results'] = $this->getRiverResults($pager, count($data['entities']));
      }

      $elements[$delta] = $element;
    }

    return $elements;
  }

  /**
   * Get the results render array for the given pager and number of found items.
   *
   * @param \Drupal\Core\Pager\Pager $pager
   *   Pager for the current river.
   * @param int $count
   *   Number of found documents.
   *
   * @return array
   *   Render array for the results.
   */
  protected function getRiverResults(Pager $pager, $count) {
    $page = $pager->getCurrentPage();
    $limit = $pager->getLimit();
    $total = $pager->getTotalItems();

    $offset = $page * $limit;
    // Range is inclusive so we start at 1.
    $start = $count > 0 ? $offset + 1 : 0;
    $end = $offset + $count;

    return [
      '#theme' => 'unocha_reliefweb_river_results',
      '#total' => $total,
      '#start' => $start,
      '#end' => $end,
    ];
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
