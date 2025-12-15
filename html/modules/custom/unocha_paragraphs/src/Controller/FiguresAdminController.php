<?php

namespace Drupal\unocha_paragraphs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\ocha_key_figures\Controller\OchaKeyFiguresController;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the Figures admin page.
 */
class FiguresAdminController extends ControllerBase {

  /**
   * List of providers.
   *
   * @var array
   */
  protected array $providers = [];

  /**
   * List of countries keyed by provider.
   *
   * @var array<string, array>
   */
  protected array $countriesByProvider = [];

  /**
   * List of ocha presences keyed by provider.
   *
   * @var array<string, array>
   */
  protected array $ochaPresencesByProvider = [];

  /**
   * List of ocha presence years keyed by provider.
   *
   * @var array<string, array>
   */
  protected array $ochaPresenceYearsByProvider = [];

  /**
   * Destination URL for the figures admin page.
   *
   * @var string
   */
  protected string $destinationUrl = '';

  /**
   * Constructs a FiguresAdminController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\ocha_key_figures\Controller\OchaKeyFiguresController $ochaKeyFiguresApiClient
   *   The OCHA key figures API client.
   */
  public function __construct(
    protected Connection $database,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected RequestStack $requestStack,
    protected OchaKeyFiguresController $ochaKeyFiguresApiClient,
  ) {
    // Retrieve the current URL including the query string.
    $this->destinationUrl = $this->requestStack->getCurrentRequest()->getRequestUri();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('request_stack'),
      $container->get('ocha_key_figures.key_figures_controller')
    );
  }

  /**
   * Get the parent node of a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Parent node.
   */
  protected function getParagraphParentNode(ParagraphInterface $paragraph) {
    $parent = $paragraph->getParentEntity();
    while (isset($parent) && !($parent instanceof NodeInterface)) {
      if ($parent instanceof ParagraphInterface) {
        $parent = $parent->getParentEntity();
      }
      else {
        $parent = NULL;
      }
    }
    return $parent;
  }

  /**
   * Lists all figures paragraphs grouped by parent node.
   *
   * @return array
   *   A render array.
   */
  public function listFigures() {
    $request = $this->requestStack->getCurrentRequest();
    $storage = $this->entityTypeManager()->getStorage('paragraph');

    // Get filter values from query parameters.
    $filters = [
      'node' => $request->query->get('node', ''),
      'provider' => $request->query->get('provider', ''),
      'year' => $request->query->get('year', ''),
    ];

    // Build filter form.
    $build['filters'] = $this->buildFilterForm($filters);

    // Get the figures paragraphs.
    $query = $this->database->select('paragraphs_item_field_data', 'pifd');
    $query->addField('pifd', 'id', 'paragraph_id');
    $query->condition('pifd.type', 'figures', '=');

    // Limit to the paragraphs referenced by the current revision of nodes.
    $query->innerJoin('node__field_custom_content', 'nfc', 'pifd.id = nfc.field_custom_content_target_id');

    // Join the node table to be able to sort by node title.
    $query->innerJoin('node_field_data', 'nfd', 'nfc.entity_id = nfd.nid');

    // Apply node filter (by ID or title).
    if (!empty($filters['node'])) {
      $node_filter = $filters['node'];
      // Check if it's a numeric ID.
      if (is_numeric($node_filter)) {
        $query->condition('nfd.nid', $node_filter, '=');
      }
      else {
        // Filter by title (case-insensitive partial match).
        $query->condition('nfd.title', '%' . $this->database->escapeLike($node_filter) . '%', 'LIKE');
      }
    }

    // Join the figures fields to restrict to paragraphs with figures data.
    $query->leftJoin('paragraph__field_figures', 'pff', 'pifd.id = pff.entity_id');
    $query->leftJoin('paragraph__field_presence_figures', 'pfpf', 'pifd.id = pfpf.entity_id');
    $query->condition($query->orConditionGroup()
      ->isNotNull('pff.entity_id')
      ->isNotNull('pfpf.entity_id'));

    // Sort by node ID and paragraph ID.
    $query->orderBy('nfd.title', 'ASC');
    $query->orderBy('pifd.id', 'ASC');

    // Use a pager to limit the number of results per page.
    $query = $query->extend(PagerSelectExtender::class)->limit(30);

    // Retrieve the paragraph IDs.
    $paragraph_ids = $query->execute()->fetchCol();
    if (empty($paragraph_ids)) {
      $build['empty'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No figures paragraphs found.'),
      ];
      return $build;
    }

    // Load the paragraphs.
    $paragraphs = $storage->loadMultiple($paragraph_ids);

    // Group paragraphs by parent node.
    $grouped = [];
    foreach ($paragraphs as $paragraph) {
      $parent = $this->getParagraphParentNode($paragraph);
      $parent_id = $parent ? $parent->id() : 0;

      if (!isset($grouped[$parent_id])) {
        $grouped[$parent_id] = [
          'parent' => $parent,
          'paragraphs' => [],
        ];
      }

      $grouped[$parent_id]['paragraphs'][$paragraph->id()] = $paragraph;
    }

    // Sort grouped data by parent ID, then paragraph ID.
    ksort($grouped);
    foreach ($grouped as &$group) {
      ksort($group['paragraphs']);
    }

    // Build rows for the table.
    $rows = [];
    foreach ($grouped as $parent_id => $group) {
      $parent = $group['parent'];

      foreach ($group['paragraphs'] as $paragraph) {
        // Process country figures.
        if ($paragraph->hasField('field_figures') && !$paragraph->get('field_figures')->isEmpty()) {
          foreach ($paragraph->get('field_figures') as $item) {
            // Apply provider and year filters.
            if ($this->matchesFilters($item, $filters)) {
              $row = $this->buildRow($parent, $paragraph, $item);
              if ($row) {
                $rows[] = $row;
              }
            }
          }
        }

        // Process presence figures.
        if ($paragraph->hasField('field_presence_figures') && !$paragraph->get('field_presence_figures')->isEmpty()) {
          foreach ($paragraph->get('field_presence_figures') as $item) {
            // Apply provider and year filters.
            if ($this->matchesFilters($item, $filters)) {
              $row = $this->buildRow($parent, $paragraph, $item);
              if ($row) {
                $rows[] = $row;
              }
            }
          }
        }
      }
    }

    // Build table.
    $header = [
      'page' => $this->t('Page'),
      'status' => $this->t('Status'),
      'paragraph' => $this->t('Paragraph'),
      'enabled' => $this->t('Enabled'),
      'field_type' => $this->t('Field Type'),
      'provider' => $this->t('Provider'),
      'location' => $this->t('Location'),
      'year' => $this->t('Year'),
      'figure_id' => $this->t('Key Figure'),
      'label' => $this->t('Label'),
      'value' => $this->t('Value'),
      'unit' => $this->t('Unit'),
      'most_recent_data_year' => $this->t('Most Recent Data'),
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No figures paragraphs found.'),
      '#sticky' => TRUE,
      '#attributes' => [
        'class' => ['figures-admin-table'],
      ],
      '#attached' => [
        'library' => [
          'unocha_paragraphs/figures_admin',
        ],
      ],
    ];

    // Add pager.
    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Build a table row from a field item.
   *
   * @param ?\Drupal\node\NodeInterface $parent
   *   Parent node.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   Field item.
   *
   * @return array|null
   *   Table row or NULL if data unavailable.
   */
  protected function buildRow(
    ?NodeInterface $parent,
    ParagraphInterface $paragraph,
    FieldItemInterface $item,
  ): ?array {
    $field_type = $item->getFieldDefinition()->getType();
    if ($field_type !== 'key_figure' && $field_type !== 'key_figure_presence') {
      return NULL;
    }

    $provider = $item->getFigureProvider() ?? '';
    $year = $item->getFigureYear() ?? '';
    $figure_id = $item->getFigureId() ?? '';

    $label = $item->getFigureLabel();
    $value = $item->getFigureValue();
    $unit = $item->getFigureUnit();

    $location = match ($field_type) {
      'key_figure' => $item->getFigureCountry() ?? '',
      'key_figure_presence' => $item->getFigureOchaPresence() ?? '',
      default => '',
    };

    // Try to fetch actual value from API if available.
    if ($provider && $provider !== 'manual' && $location && $figure_id && $year) {
      $data = $this->getFigureData($field_type, $provider, $location, $year, $figure_id);

      // Manually set label takes precedence over the API one.
      $label ??= $data['label'] ?? NULL;
      $value = $data['value'] ?? NULL;
      $unit = $data['unit'] ?? NULL;

      $most_recent_data_year = $this->getMostRecentFigureDataYear($field_type, $provider, $location, $figure_id);
    }

    $label ??= $this->t('No data');
    $value ??= $this->t('No data');
    $unit ??= '';
    $most_recent_data_year ??= $this->t('N/A');

    // Generate a link to the paragrpah in the parent node edit form.
    if ($parent) {
      $page_link = $parent->toLink($parent->label(), 'edit-form', [
        // @see \Drupal\unocha_paragraphs\js\node-form-hash-scroll.js
        // @see unocha_paragraphs_preprocess_paragraph()
        'fragment' => 'paragraph-' . $paragraph->uuid(),
        // Return to the current figures admin page.
        'query' => [
          'destination' => $this->destinationUrl,
        ],
      ])->toString();
      $page_status = $parent->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    }
    else {
      $page_link = $this->t('No parent node');
      $page_status = $this->t('N/A');
    }

    // Retrieve the paragraph title.
    $paragraph_title = $this->getParagraphTitle($paragraph) ?? $this->t('Paragraph @id', ['@id' => $paragraph->id()]);

    // Get the paragraph status.
    $enabled = $paragraph->isPublished() ? $this->t('Yes') : $this->t('No');

    // Get the field type label.
    $field_type_label = match ($field_type) {
      'key_figure' => $this->t('Country'),
      'key_figure_presence' => $this->t('Presence'),
      default => $this->t('Unknown'),
    };

    // Retrieve the provider label.
    $provider_label = $this->getProviderLabel($provider);

    // Retrieve the location label.
    $location_label = match ($field_type) {
      'key_figure' => $this->getCountryLabel($provider, $location),
      'key_figure_presence' => $this->getOchaPresenceLabel($provider, $location),
      default => $location,
    };

    // Adjust the year.
    $year = match ((int) $year) {
      1 => $this->t('Any year'),
      2 => $this->t('Current year'),
      default => $year,
    };

    return [
      'page' => $page_link,
      'status' => $page_status,
      'paragraph' => $paragraph_title,
      'enabled' => $enabled,
      'field_type' => $field_type_label,
      'provider' => $provider_label,
      'location' => $location_label,
      'year' => $year,
      'figure_id' => $figure_id,
      'label' => $label,
      'value' => $value,
      'unit' => $unit,
      'most_recent_data_year' => $most_recent_data_year,
    ];
  }

  /**
   * Get the most recent data year for a figure.
   *
   * @param string $type
   *   Type of figure: 'key_figure' or 'key_figure_presence'.
   * @param string $provider
   *   Provider.
   * @param string $location
   *   Location.
   * @param string $figure_id
   *   Figure ID.
   *
   * @return string|null
   *   Most recent data year or NULL if not found.
   */
  protected function getMostRecentFigureDataYear(string $type, string $provider, string $location, string $figure_id): ?string {
    $prefix = $this->ochaKeyFiguresApiClient->getPrefix($provider);

    if ($type === 'key_figure') {
      $endpoint = $prefix;
      // For country figures, we can skip the year parameter to get all the
      // available years.
      $query = [
        'iso3' => $location,
        'archived' => 0,
        'figure_id' => $figure_id,
        'order' => [
          'year' => 'desc',
        ],
      ];
    }
    elseif ($type === 'key_figure_presence') {
      $endpoint = $prefix . '/ocha-presences/' . $location;
      $query = [
        // For the OCHA presence figures, we need to provide a range of years.
        'year' => $this->getOchaPresenceYears($provider, $location),
        'figure_id' => $figure_id,
        'order' => [
          'year' => 'DESC',
        ],
      ];
    }

    $data = $this->ochaKeyFiguresApiClient->getData($endpoint, $query);
    $data = reset($data);
    return $data['year'] ?? NULL;
  }

  /**
   * Get figure data from the API.
   *
   * @param string $type
   *   Type of figure: 'key_figure' or 'key_figure_presence'.
   * @param string $provider
   *   Provider.
   * @param string $location
   *   Location.
   * @param string $year
   *   Year.
   * @param string $figure_id
   *   Figure ID.
   *
   * @return array
   *   Figure data.
   */
  protected function getFigureData(string $type, string $provider, string $location, string $year, string $figure_id): array {
    $data = [];
    if ($type === 'key_figure') {
      $data = $this->ochaKeyFiguresApiClient->getFigureByFigureId($provider, $location, $year, $figure_id) ?: [];
    }
    elseif ($type === 'key_figure_presence') {
      $data = $this->ochaKeyFiguresApiClient->getOchaPresenceFigureByFigureId($provider, $location, $year, $figure_id) ?: [];
    }
    return $data;
  }

  /**
   * Get paragraph title.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph.
   *
   * @return string|null
   *   Paragraph title.
   */
  protected function getParagraphTitle(ParagraphInterface $paragraph): ?string {
    // Check the current paragraph first.
    if ($paragraph->hasField('field_title') && !$paragraph->get('field_title')->isEmpty()) {
      return $paragraph->get('field_title')->value;
    }

    // Check if this paragraph is nested in a layout paragraph via parent_uuid.
    $layout_parent = $this->getLayoutParagraphParent($paragraph);
    if ($layout_parent && $layout_parent instanceof ParagraphInterface) {
      $title = $this->getParagraphTitle($layout_parent);
      if (isset($title)) {
        return $title;
      }
    }

    // Check the direct parent entity (standard paragraph hierarchy).
    $parent = $paragraph->getParentEntity();
    if ($parent instanceof ParagraphInterface) {
      $title = $this->getParagraphTitle($parent);
      if (isset($title)) {
        return $title;
      }
    }

    return NULL;
  }

  /**
   * Get the layout paragraph parent if this paragraph is nested in one.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph.
   *
   * @return \Drupal\paragraphs\ParagraphInterface|null
   *   Layout paragraph parent or NULL if not found.
   */
  protected function getLayoutParagraphParent(ParagraphInterface $paragraph): ?ParagraphInterface {
    // Get layout paragraphs behavior settings.
    $behaviors_settings = $paragraph->getAllBehaviorSettings();
    $layout_behavior_settings = $behaviors_settings['layout_paragraphs'] ?? [];
    $parent_uuid = $layout_behavior_settings['parent_uuid'] ?? NULL;
    if (empty($parent_uuid)) {
      return NULL;
    }

    // Get the parent entity to find the field containing paragraphs.
    $parent_entity = $paragraph->getParentEntity();
    if (!$parent_entity) {
      return NULL;
    }

    // Find the paragraph field that contains this paragraph and search for
    // the layout paragraph with the matching UUID.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      $parent_entity->getEntityTypeId(),
      $parent_entity->bundle()
    );

    foreach ($field_definitions as $field_name => $field_definition) {
      $field_type = $field_definition->getType();
      if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
        $field_storage = $field_definition->getFieldStorageDefinition();
        $target_type = $field_storage->getSetting('target_type');

        if ($target_type === 'paragraph' && $parent_entity->hasField($field_name)) {
          $field_items = $parent_entity->get($field_name);
          foreach ($field_items as $field_item) {
            if ($field_item->entity && $field_item->entity->uuid() === $parent_uuid) {
              return $field_item->entity;
            }
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Get the label for a provider.
   *
   * @param string $provider
   *   Provider.
   *
   * @return string
   *   Provider label.
   */
  protected function getProviderLabel(string $provider): string {
    if (empty($this->providers)) {
      // Get list of providers.
      $this->providers = [
        'manual' => $this->t('Manual'),
      ] + $this->ochaKeyFiguresApiClient->getSupportedProviders();
    }

    return $this->providers[$provider] ?? $provider;
  }

  /**
   * Get the label for a country.
   *
   * @param string $provider
   *   Provider.
   * @param string $country
   *   Country.
   *
   * @return string
   *   Country label.
   */
  protected function getCountryLabel(string $provider, string $country): string {
    if (!isset($this->countriesByProvider[$provider])) {
      $this->countriesByProvider[$provider] = $this->ochaKeyFiguresApiClient
        ->getCountries($provider) ?? [];
    }

    return $this->countriesByProvider[$provider][$country] ?? $country;
  }

  /**
   * Get the label for an ocha presence.
   *
   * @param string $provider
   *   Provider.
   * @param string $ocha_presence
   *   Ocha presence.
   *
   * @return string
   *   Ocha presence label.
   */
  protected function getOchaPresenceLabel(string $provider, string $ocha_presence): string {
    if (!isset($this->ochaPresencesByProvider[$provider])) {
      $this->ochaPresencesByProvider[$provider] = $this->ochaKeyFiguresApiClient
        ->getOchaPresencesByProvider($provider) ?? [];
    }

    return $this->ochaPresencesByProvider[$provider][$ocha_presence] ?? $ocha_presence;
  }

  /**
   * Get OCHA Presence years.
   *
   * @param string $provider
   *   Provider.
   * @param string $ocha_presence
   *   Ocha presence.
   *
   * @return array
   *   OCHA Presence years.
   */
  protected function getOchaPresenceYears(string $provider, string $ocha_presence): array {
    if (!isset($this->ochaPresenceYearsByProvider[$provider])) {
      $years = $this->ochaKeyFiguresApiClient->getOchaPresenceYearsByProvider($provider, $ocha_presence) ?: [];
      $this->ochaPresenceYearsByProvider[$provider] = array_keys($years);
    }

    return $this->ochaPresenceYearsByProvider[$provider];
  }

  /**
   * Build the filter form.
   *
   * @param array $filters
   *   Current filter values.
   *
   * @return array
   *   Form render array.
   */
  protected function buildFilterForm(array $filters): array {
    // Get list of providers for the select field.
    $providers = [
      '' => $this->t('- Any -'),
      'manual' => $this->t('Manual'),
    ] + $this->ochaKeyFiguresApiClient->getSupportedProviders();

    $current_year = date('Y');

    // Retrieve the minimum year from the figures and presence figures fields.
    $figures_minimum_year = $this->getFiguresMinimumYear('figures');
    $presence_figures_minimum_year = $this->getFiguresMinimumYear('presence_figures');
    $minimum_year = min($figures_minimum_year, $presence_figures_minimum_year, $current_year);

    // Get available years (from current year back to the minimum year).
    $years = [
      '' => $this->t('- Any -'),
      '1' => $this->t('Any year'),
      '2' => $this->t('Current year'),
    ];
    for ($year = $current_year; $year >= $minimum_year; $year--) {
      $years[$year] = $year;
    }

    $request = $this->requestStack->getCurrentRequest();

    // Wrap in a form element for GET submission.
    $form = [
      '#type' => 'form',
      '#method' => 'get',
      '#action' => $request->getRequestUri(),
      '#attributes' => [
        'class' => ['figures-admin-filters'],
      ],
    ];

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filters'),
      '#attributes' => [
        'class' => ['form--inline', 'clearfix'],
      ],
    ];

    $form['filters']['node'] = [
      '#type' => 'textfield',
      '#name' => 'node',
      '#title' => $this->t('Node'),
      '#value' => $filters['node'],
      '#size' => 30,
      '#attributes' => [
        'placeholder' => $this->t('Node ID or title'),
      ],
    ];

    $form['filters']['provider'] = [
      '#type' => 'select',
      '#name' => 'provider',
      '#title' => $this->t('Provider'),
      '#options' => $providers,
      '#value' => $filters['provider'],
    ];

    $form['filters']['year'] = [
      '#type' => 'select',
      '#name' => 'year',
      '#title' => $this->t('Year'),
      '#options' => $years,
      '#value' => $filters['year'],
    ];

    $form['filters']['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-actions'],
      ],
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#name' => 'filter',
    ];

    $form['filters']['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('unocha_paragraphs.figures_admin'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Use #parents to flatten the form element names for GET submission.
    $form['filters']['node']['#parents'] = ['node'];
    $form['filters']['provider']['#parents'] = ['provider'];
    $form['filters']['year']['#parents'] = ['year'];

    // Preserve other query parameters.
    $query_params = $request->query->all();
    unset($query_params['node'], $query_params['provider'], $query_params['year'], $query_params['page']);
    foreach ($query_params as $key => $value) {
      $form[$key] = [
        '#type' => 'hidden',
        '#value' => $value,
      ];
    }

    return $form;
  }

  /**
   * Check if a field item matches the current filters.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   Field item.
   * @param array $filters
   *   Filter values.
   *
   * @return bool
   *   TRUE if the item matches the filters, FALSE otherwise.
   */
  protected function matchesFilters(FieldItemInterface $item, array $filters): bool {
    // Check provider filter.
    if (!empty($filters['provider'])) {
      $provider = $item->getFigureProvider() ?? '';
      if ($provider !== $filters['provider']) {
        return FALSE;
      }
    }

    // Check year filter.
    if (!empty($filters['year'])) {
      $year = $item->getFigureYear() ?? '';
      $filter_year = $filters['year'];

      // Handle special year values.
      if ($filter_year === '1') {
        // "Any year" - year filter passes, provider already checked.
        return TRUE;
      }
      elseif ($filter_year === '2') {
        // "Current year" - convert to actual current year.
        $filter_year = (string) date('Y');
      }

      // Compare years (both are now specific year values).
      if ($year !== $filter_year) {
        return FALSE;
      }
    }

    // All filters passed.
    return TRUE;
  }

  /**
   * Get the minimum year for a type of figure.
   *
   * @param string $type
   *   Type of figure: 'figures' or 'presence_figures'.
   *
   * @return int
   *   Minimum year or current year if no data is available.
   */
  protected function getFiguresMinimumYear(string $type): int {
    $current_year = date('Y');
    $table_name = 'paragraph__field_' . $type;
    $field_name = 'field_' . $type . '_year';
    $query = $this->database->select($table_name, $table_name);
    $query->addExpression('MIN(' . $table_name . '.' . $field_name . ')', 'min_year');
    $query->addExpression('MAX(' . $table_name . '.' . $field_name . ')', 'max_year');
    $query->condition($table_name . '.' . $field_name, 2, '>');
    $query->condition($table_name . '.' . $field_name, $current_year, '<=');
    return (int) ($query->execute()?->fetchField() ?? $current_year);
  }

}
