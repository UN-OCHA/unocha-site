<?php

namespace Drupal\unocha_reliefweb\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\unocha_reliefweb\Helpers\UrlHelper;
use Drupal\unocha_utility\Helpers\DateHelper;
use Drupal\unocha_utility\Helpers\HtmlSanitizer;
use Drupal\unocha_utility\Helpers\HtmlSummarizer;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * ReliefWeb documents service class.
 */
class ReliefWebDocuments {

  use StringTranslationTrait;

  /**
   * ReliefWeb API config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The ReliefWeb API Client.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebApiClient
   */
  protected $apiClient;

  /**
   * The ReliefWeb API Converter.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebApiConverter
   */
  protected $apiConverter;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\unocha_reliefweb\Services\ReliefWebApiClient $reliefweb_api_client
   *   The ReliefWeb API Client.
   * @param \Drupal\unocha_reliefweb\Services\ReliefWebApiConverter $reliefweb_api_converter
   *   The ReliefWeb API Converter.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ReliefWebApiClient $reliefweb_api_client,
    ReliefWebApiConverter $reliefweb_api_converter,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
    TranslationInterface $string_translation
  ) {
    $this->config = $config_factory->get('unocha_reliefweb.settings');
    $this->apiClient = $reliefweb_api_client;
    $this->apiConverter = $reliefweb_api_converter;
    $this->logger = $logger_factory->get('unocha_reliefweb');
    $this->requestStack = $request_stack;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Get the list of supported rivers and their labels.
   *
   * @return array
   *   List of river labels keyed by river and with the following as values:
   *   - river name
   *   - API resource
   *   - river label
   *   - entity bundle (on reliefweb)
   */
  public function getRivers() {
    return [
      'jobs' => [
        'river' => 'jobs',
        'resource' => 'jobs',
        'label' => $this->t('Jobs'),
        'bundle' => 'job',
        'fields' => ['url_alias'],
        'parse' => [$this, 'parseJobsApiData'],
      ],
      'training' => [
        'river' => 'training',
        'resource' => 'training',
        'label' => $this->t('Training'),
        'bundle' => 'training',
        'fields' => ['url_alias'],
        'parse' => [$this, 'parseReportsApiData'],
      ],
      'updates' => [
        'river' => 'udpates',
        'resource' => 'reports',
        'label' => $this->t('Updates'),
        'bundle' => 'report',
        'fields' => [
          'url_alias',
          'file',
          'image',
          'headline',
          'format',
          'date',
          'body-html',
        ],
        'filter' => [
          'field' => 'ocha_product',
        ],
        'parse' => [$this, 'parseReportsApiData'],
      ],
    ];
  }

  /**
   * Get the parsed data retrieved from the API for a single document .
   *
   * @param string $river_name
   *   The ReliefWeb river name for this document.
   * @param string $url
   *   URL of the document ont the ReliefWeb site (alias or short URL).
   * @param array $filter
   *   Optional filter to override the default river one if any.
   * @param bool $white_label
   *   Whether to white label the ReliefWeb article URL or not.
   *
   * @return array
   *   An associative array with the river information and the document entity
   *   data usable in templates.
   */
  public function getDocumentDataFromUrl($river_name, $url, array $filter = NULL, $white_label = TRUE) {
    $river = $this->getRiver($river_name);
    if (empty($river)) {
      return [];
    }

    // Filters for the URL lookup to retrieve the ReliefWeb document.
    $lookup_filter = [
      'field' => 'url_alias',
      'value' => $url,
    ];

    // Also use the "redirects" field for the lookup if instructed so.
    if (!empty($this->getConfig()->get('reliefweb_api_use_redirects'))) {
      $lookup_filter = [
        'conditions' => [
          $lookup_filter,
          [
            'field' => 'redirects',
            'value' => $url,
          ],
        ],
        'operator' => 'OR',
      ];
    }
    $payload = [
      'filter' => $lookup_filter,
      'fields' => [
        // Retrieve all the fields.
        'include' => ['*'],
      ],
    ];

    $data = $this->getDocumentsFromPayload($river, $payload, 1, $filter, $white_label);

    return [
      'river' => $river,
      'entity' => !empty($data['entities']) ? reset($data['entities']) : [],
    ];
  }

  /**
   * Get the parsed data retrieved from the API for a river.
   *
   * @param string $url
   *   The URL of the river.
   * @param int $limit
   *   Number of items to retrieve from the API. Defaults to 5.
   * @param int $offset
   *   Number of items from which to start retrieving results. Defaults to 0.
   * @param array $filter
   *   Optional filter to further limit the document to return.
   * @param bool $white_label
   *   Whether to white label the ReliefWeb article URL or not.
   *
   * @return array
   *   An associative array with the river information and the document entities
   *   data usable in templates.
   */
  public function getRiverDataFromUrl($url, $limit = 5, $offset = 0, array $filter = NULL, $white_label = TRUE) {
    $river = $this->getRiverFromUrl($url);
    if (empty($river)) {
      return [];
    }

    $payload = $this->getPayloadFromUrl($url);
    if (empty($payload)) {
      return [];
    }

    if (!empty($offset)) {
      $payload['offset'] = $offset;
    }

    return $this->getDocumentsFromPayload($river, $payload, $limit, $filter, $white_label);
  }

  /**
   * Get the parsed data retrieved from the API for the given river and paylaod.
   *
   * @param array $river
   *   River information.
   * @param array $payload
   *   API payload.
   * @param int $limit
   *   Number of items to retrieve from the API.
   * @param array $filter
   *   Optional filter to further limit the document to return.
   * @param bool $white_label
   *   Whether to white label the ReliefWeb article URL or not.
   *
   * @return array
   *   An associative array with the river information, the entities data
   *   usable in templates and the total number of resources matching the
   *   payload.
   */
  protected function getDocumentsFromPayload(array $river, array $payload, $limit, array $filter = NULL, $white_label = TRUE) {
    // Set the maximum number of items to return.
    $payload['limit'] = $limit;

    // Additional fields to return for each resource.
    if (!empty($river['fields'])) {
      foreach ($river['fields'] as $field) {
        $payload['fields']['include'][] = $field;
      }
    }

    // Combine the filters.
    $filter = $filter ?? $river['filter'] ?? [];
    if (!empty($filter)) {
      if (isset($payload['filter'])) {
        $payload['filter'] = [
          'conditions' => [
            $filter,
            $payload['filter'],
          ],
          'operator' => 'AND',
        ];
      }
      else {
        $payload['filter'] = $filter;
      }
    }

    // Get the API data.
    $data = $this->getApiClient()->request($river['resource'], $payload);

    // Parse the API data.
    $entities = !empty($data) ? call_user_func($river['parse'], $river, $data, $white_label) : [];

    return [
      'river' => $river,
      'entities' => $entities,
      'total' => $data['totalCount'] ?? count($entities),
    ];
  }

  /**
   * Get the river information.
   *
   * @param string $name
   *   River name.
   *
   * @return array
   *   River information.
   */
  public function getRiver($name) {
    return $this->getRivers()[$name] ?? [];
  }

  /**
   * Get a river from its API resource.
   *
   * @param string $resource
   *   River resource.
   *
   * @return array
   *   River information.
   *
   * @throws \Exception
   *   Exception if there is no river info for the given resource.
   */
  public function getRiverFromResource($resource) {
    foreach ($this->getRivers() as $river) {
      if ($river['resource'] === $resource) {
        return $river;
      }
    }
    throw new \Exception('Unsupported river for the API resource: ' . $resource);
  }

  /**
   * Extract the river from the river URL.
   *
   * @param string $url
   *   River URL.
   *
   * @return string
   *   River name.
   */
  public function getRiverFromUrl($url) {
    $name = !empty($url) ? trim(parse_url($url, \PHP_URL_PATH) ?: '', '/') : '';
    return $this->getRiver($name);
  }

  /**
   * Get the API payload corresponding to the river URL.
   *
   * @param string $url
   *   River URL.
   *
   * @return string
   *   ReliefWeb API payload.
   */
  public function getPayloadFromUrl($url) {
    if (!empty($url)) {
      return $this->getApiConverter()->getApiPayload($url);
    }
    return [];
  }

  /**
   * Parse the ReliefWeb API data for reports.
   *
   * @param array $river
   *   River information.
   * @param array $api_data
   *   API data.
   * @param bool $white_label
   *   Whether to white label the ReliefWeb article URL or not.
   *
   * @return array
   *   List of articles with data ready to be passed to the template.
   */
  protected function parseReportsApiData(array $river, array $api_data, $white_label = TRUE) {
    $unocha_url = $this->getUnochaUrl();

    // Retrieve the API data (with backward compatibility).
    $items = $api_data['items'] ?? $api_data['data'] ?? [];

    // Parse the entities retrieved from the API.
    $entities = [];
    foreach ($items as $item) {
      $fields = $item['fields'];

      // Title.
      $title = $fields['title'];

      // Summary.
      $summary = '';
      if (!empty($fields['headline']['summary'])) {
        // The headline summary is plain text.
        $summary = $fields['headline']['summary'];
      }
      elseif (!empty($fields['body-html'])) {
        // Summarize the body. The average headline summary length is 182
        // characters so 200 characters sounds reasonable as there is often
        // date or location information at the beginning of the normal body
        // text, so we add a bit of margin to have more useful information in
        // the generated summary.
        $body = HtmlSanitizer::sanitize($fields['body-html']);
        $summary = HtmlSummarizer::summarize($body, 200);
      }

      // Determine document type.
      $format = '';
      if (!empty($fields['format'])) {
        if (isset($fields['format']['name'])) {
          $format = $fields['format']['name'];
        }
        elseif (isset($fields['format'][0]['name'])) {
          $format = $fields['format'][0]['name'];
        }
      }

      // Set the summary if it's empty but there are attachments.
      if (empty($summary) && !empty($fields['file'])) {
        switch ($format) {
          case 'Map':
            $summary = $this->t('Please refer to the attached Map.');
            break;

          case 'Infographic':
            $summary = $this->t('Please refer to the attached Infographic.');
            break;

          case 'Interactive':
            $summary = $this->t('Please refer to the linked Interactive Content.');
            break;

          default:
            if (count($fields['file']) > 1) {
              $summary = $this->t('Please refer to the attached files.');
              break;
            }
            else {
              $summary = $this->t('Please refer to the attached file.');
              break;
            }
        }
      }

      // Tags (countries, sources etc.).
      $tags = $this->parseRiverArticleTags($fields, [
        'country' => 'country',
        'source' => 'source',
        'language' => 'language',
        'ocha_product' => 'ocha_product',
      ]);

      // Base article data.
      $data = [
        'id' => $item['id'],
        'bundle' => $river['bundle'],
        'title' => $title,
        'summary' => $summary,
        'format' => $format,
        'tags' => $tags,
        'body-html' => $fields['body-html'] ?? '',
      ];

      // Url to the article.
      $data['url'] = $white_label ? UrlHelper::getUnochaUrlFromReliefWebUrl($fields['url_alias']) : $fields['url_alias'];

      // Dates.
      $data += $this->parseRiverArticleDates($fields, [
        'created' => 'posted',
        'original' => 'published',
      ]);

      // Attachments.
      if (!empty($fields['file'])) {
        $data['attachments'] = $fields['file'];
        // Change the URLs of the attachment to be an unocha.org URL.
        if ($white_label) {
          $this->getApiClient()->updateApiUrls($data['attachments'], $unocha_url);
        }

        // Prepare the previews.
        foreach ($data['attachments'] as $index => $attachment) {
          if (isset($attachment['preview'])) {
            $preview = $attachment['preview'];
            $version = $preview['version'] ?? $attachment['id'] ?? 0;

            // Add a the preview version to the URLs to ensure freshness.
            foreach ($preview as $key => $value) {
              if (strpos($key, 'url') === 0) {
                $preview[$key] = UrlHelper::stripDangerousProtocols($value) . '?' . $version;
              }
            }
            $data['attachments'][$index]['preview'] = $preview;

            // Keep track of the first attachment with a preview to use as a
            // thumbnail of the report.
            if (!isset($data['preview'])) {
              $preview_url = $preview['url-thumb'] ?? $preview['url-small'] ?? '';
              if (isset($preview_url)) {
                $data['preview'] = [
                  'url' => $preview['url-thumb'] ?? $preview['url-small'],
                  // We don't have any good label/description for the file
                  // previews so we use an empty alt to mark them as decorative
                  // so that assistive technologies will ignore them.
                  'alt' => '',
                ];
              }
            }
          }
        }
      }

      // Image.
      if (!empty($fields['image'])) {
        $data['image'] = $fields['image'];
        // Change the URLs of the image to be an unocha.org URL.
        if ($white_label) {
          $this->getApiClient()->updateApiUrls($data['image'], $unocha_url);
        }
      }

      // Compute the language code from the resource's data.
      $data['langcode'] = $this->getEntityLanguageCode($data);

      $entities[$item['id']] = $data;
    }

    return $entities;
  }

  /**
   * Parse the ReliefWeb API data for jobs.
   *
   * @param array $river
   *   River information.
   * @param array $api_data
   *   API data.
   * @param bool $white_label
   *   Whether to white label the ReliefWeb article URL or not.
   *
   * @return array
   *   List of articles with data ready to be passed to the template.
   *
   * @todo This is mostly for reference and would need adjustments if ever used.
   */
  protected function parseJobsApiData(array $river, array $api_data, $white_label = FALSE) {
    // Retrieve the API data (with backward compatibility).
    $items = $api_data['items'] ?? $api_data['data'] ?? [];

    // Parse the entities retrieved from the API.
    $entities = [];
    foreach ($items as $item) {
      $fields = $item['fields'];

      // Title.
      $title = $fields['title'];

      // Summary.
      $summary = '';
      if (!empty($fields['body-html'])) {
        $body = HtmlSanitizer::sanitize($fields['body-html']);
        $summary = HtmlSummarizer::summarize($body, 200);
      }

      // Tags (countries, sources etc.).
      $tags = $this->parseRiverArticleTags($fields, [
        'country' => 'country',
        'source' => 'source',
      ]);

      // Base article data.
      $data = [
        'id' => $item['id'],
        'bundle' => $river['bundle'],
        'title' => $title,
        'summary' => $summary,
        'tags' => $tags,
      ];

      // Url to the article.
      $data['url'] = $white_label ? UrlHelper::getUnochaUrlFromReliefWebUrl($fields['url_alias']) : $fields['url_alias'];

      // Dates.
      $data += $this->parseRiverArticleDates($fields, [
        'created' => 'posted',
        'closing' => 'closing',
      ]);

      // Compute the language code for the resource's data.
      $data['langcode'] = $this->getEntityLanguageCode($data);

      $entities[$item['id']] = $data;
    }

    return $entities;
  }

  /**
   * Parse the ReliefWeb API data for training.
   *
   * @param array $river
   *   River information.
   * @param array $api_data
   *   API data.
   * @param bool $white_label
   *   Whether to white label the ReliefWeb article URL or not.
   *
   * @return array
   *   List of articles with data ready to be passed to the template.
   *
   * @todo This is mostly for reference and would need adjustments if ever used.
   */
  protected function parseTrainingApiData(array $river, array $api_data, $white_label = TRUE) {
    // Retrieve the API data (with backward compatibility).
    $items = $api_data['items'] ?? $api_data['data'] ?? [];

    // Parse the entities retrieved from the API.
    $entities = [];
    foreach ($items as $item) {
      $fields = $item['fields'];

      // Title.
      $title = $fields['title'];

      // Summary.
      $summary = '';
      if (!empty($fields['body-html'])) {
        $body = HtmlSanitizer::sanitize($fields['body-html']);
        $summary = HtmlSummarizer::summarize($body, 200);
      }

      // Tags (countries, sources etc.).
      $tags = $this->parseRiverArticleTags($fields, [
        'country' => 'country',
        'source' => 'source',
        'language' => 'language',
      ]);

      // Base article data.
      $data = [
        'id' => $item['id'],
        'bundle' => $river['bundle'],
        'title' => $title,
        'summary' => $summary,
        'tags' => $tags,
      ];

      // Url to the article.
      $data['url'] = $white_label ? UrlHelper::getUnochaUrlFromReliefWebUrl($fields['url_alias']) : $fields['url_alias'];

      // Dates.
      $data += $this->parseRiverArticleDates($fields, [
        'created' => 'created',
        'start' => 'start',
        'end' => 'end',
        'registration' => 'registration',
      ]);

      // Compute the language code for the resource's data.
      $data['langcode'] = $$this->getEntityLanguageCode($data);

      $entities[$item['id']] = $data;
    }

    return $entities;
  }

  /**
   * Parse the tags for a river article from the API fields data.
   *
   * @param array $fields
   *   Field data from the ReliefWeb API.
   * @param array $list
   *   List of fields to parse keyed by field name and with tag names as values.
   *
   * @return array
   *   List of tags.
   *
   * @todo make the tags link to somewhere like on ReliefWeb?
   */
  protected function parseRiverArticleTags(array $fields, array $list) {
    $tags = [];

    foreach ($list as $field => $tag) {
      $data = [];
      switch ($field) {
        // Countries.
        case 'country':
          foreach ($fields[$field] ?? [] as $item) {
            $data[] = [
              'name' => $item['name'],
              'shortname' => $item['shortname'] ?? $item['name'],
              'code' => $item['iso3'] ?? '',
              'main' => !empty($item['primary']),
            ];
          }
          break;

        // Sources.
        case 'source':
          foreach ($fields[$field] ?? [] as $item) {
            $data[] = [
              'name' => $item['name'],
              'shortname' => $item['shortname'] ?? $item['name'],
            ];
          }
          break;

        // Languages.
        case 'language':
          foreach ($fields[$field] ?? [] as $item) {
            $data[] = [
              'name' => $item['name'],
              'code' => $item['code'],
            ];
          }
          break;

        // Other more simple tags.
        default:
          foreach ($fields[$field] ?? [] as $item) {
            $data[] = [
              'name' => $item['name'],
            ];
          }
      }
      if (!empty($data)) {
        $tags[$tag] = $data;
      }
    }

    return $tags;
  }

  /**
   * Parse the dates for a river article from the API fields data.
   *
   * @param array $fields
   *   Field data from the ReliefWeb API.
   * @param array $list
   *   Fields to parse keyed by field name and with date names as values.
   *
   * @return array
   *   List of dates.
   */
  protected function parseRiverArticleDates(array $fields, array $list) {
    $dates = [];
    foreach ($list as $field => $date) {
      if (isset($fields['date'][$field])) {
        $dates[$date] = DateHelper::getDateObject($fields['date'][$field]);
      }
    }
    return $dates;
  }

  /**
   * Get the language code from the parse API data.
   *
   * @param array $data
   *   Parsed API data.
   *
   * @return string
   *   The language code for the entity.
   */
  protected function getEntityLanguageCode(array $data) {
    if (isset($data['langcode'])) {
      $langcode = $data['langcode'];
    }
    // Extract the main language code from the entity language tag.
    elseif (isset($data['tags']['language'])) {
      // English has priority over the other languages. If not present we
      // just get the first language code in the list.
      foreach ($data['tags']['language'] as $item) {
        if (isset($item['code'])) {
          if ($item['code'] === 'en') {
            $langcode = 'en';
            break;
          }
          elseif (!isset($langcode)) {
            $langcode = $item['code'];
          }
        }
      }
    }
    return $langcode ?? 'en';
  }

  /**
   * Get the UNOCHA URL.
   *
   * @return string
   *   UNOCHA URL.
   */
  protected function getUnochaUrl() {
    return $this->getRequestStack()->getCurrentRequest()->getSchemeAndHttpHost() . '/';
  }

  /**
   * Get the ReliefWeb API Client.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The ReliefWeb API Config.
   */
  protected function getConfig() {
    return $this->config;
  }

  /**
   * Get the ReliefWeb API Client.
   *
   * @return \Drupal\unocha_reliefweb\Services\ReliefWebApiClient
   *   The ReliefWeb API Client.
   */
  protected function getApiClient() {
    return $this->apiClient;
  }

  /**
   * Get the ReliefWeb API Converter.
   *
   * @return \Drupal\unocha_reliefweb\Services\ReliefWebApiConverter
   *   The ReliefWeb API Converter.
   */
  protected function getApiConverter() {
    return $this->apiConverter;
  }

  /**
   * Get the request stack.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack.
   */
  protected function getRequestStack() {
    return $this->requestStack;
  }

}
