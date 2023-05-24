<?php

namespace Drupal\unocha_figures\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;

/**
 * OCHA Key Figures API client service class.
 */
class OchaKeyFiguresApiClient {

  /**
   * The default cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * ReliefWeb API config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Map API resources to cache tags.
   *
   * @var array
   */
  protected static $cacheTags = [];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(
    CacheBackendInterface $cache_backend,
    ConfigFactoryInterface $config_factory,
    TimeInterface $time,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->cache = $cache_backend;
    $this->config = $config_factory->get('unocha_figures.settings');
    $this->time = $time;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('unocha_figures');
  }

  /**
   * Get the list of supported figure providers.
   *
   * @return array
   *   List of suported providers.
   */
  public static function getSupportedProviders() {
    return [
      'fts' => t('FTS'),
      'cbpf' => t('CBPF'),
      'cerf' => t('CERF'),
      'idps' => t('IDPS'),
      'inform' => t('INFORM'),
      'inform-risk' => t('INFORM RISK'),
      'rw-crisis' => t('ReliefWeb Crisis Figures'),
    ];
  }

  /**
   * Perform a GET request against the OCHA Key Figures API.
   *
   * @param string $resource
   *   API resource endpoint (ex: reports).
   * @param array $parameters
   *   API request parameters.
   * @param bool $decode
   *   Whether to decode (json) the output or not.
   * @param int $timeout
   *   Request timeout.
   * @param bool $cache_enabled
   *   Whether to cache the queries or not.
   *
   * @return array|null
   *   The data from the API response or NULL in case of error.
   */
  public function request($resource, array $parameters = [], $decode = TRUE, $timeout = 5, $cache_enabled = TRUE) {
    $queries = [
      $resource => [
        'resource' => $resource,
        'parameters' => $parameters,
      ],
    ];

    $results = $this->requestMultiple($queries, $decode, $timeout, $cache_enabled);
    return $results[$resource];
  }

  /**
   * Perform parallel GET queries to the API.
   *
   * @param array $queries
   *   List of queries to perform in parallel. Each item is an associative
   *   array with the resource and the query parameters.
   * @param bool $decode
   *   Whether to decode (json) the output or not.
   * @param int $timeout
   *   Request timeout.
   * @param bool $cache_enabled
   *   Whether to cache the queries or not.
   *
   * @return array
   *   Return array where each item contains the response to the corresponding
   *   query to the API.
   *
   * @see https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests
   */
  public function requestMultiple(array $queries, $decode = TRUE, $timeout = 5, $cache_enabled = TRUE) {
    $results = [];
    $api_url = $this->config->get('ocha_key_figures_api_url');
    $api_key = $this->config->get('ocha_key_figures_api_key');
    $appname = $this->config->get('ocha_key_figures_api_appname') ?: 'unocha.org';
    $cache_enabled = $cache_enabled && ($this->config->get('ocha_key_figures_api_cache_enabled') ?? TRUE);
    $cache_lifetime = $this->config->get('ocha_key_figures_api_cache_lifetime') ?? 300;
    $verify_ssl = $this->config->get('ocha_key_figures_api_verify_ssl');

    // Initialize the result array and retrieve the data for the cached queries.
    $cache_ids = [];
    foreach ($queries as $index => $query) {
      $parameters = $query['parameters'] ?? [];

      // Sanitize the query parameters.
      if (is_array($parameters)) {
        $parameters = static::sanitizeParameters($parameters);
      }

      // Update the query parameters.
      $queries[$index]['parameters'] = $parameters;

      // Attempt to get the data from the cache.
      $results[$index] = NULL;
      if ($cache_enabled) {
        // Retrieve the cache id for the query.
        $cache_id = static::getCacheId($query['resource'], $parameters);
        $cache_ids[$index] = $cache_id;
        // Attempt to retrieve the cached data for the query.
        $cache = $this->cache->get($cache_id);
        if (isset($cache->data)) {
          $results[$index] = $cache->data;
        }
      }
    }

    // Prepare the requests.
    $promises = [];
    foreach ($queries as $index => $query) {
      // Skip queries with cached data.
      if (isset($results[$index])) {
        continue;
      }

      $url = $api_url . '/' . $query['resource'];
      $parameters = $query['parameters'] ?? [];

      // Skip the request if something is wrong with the parameters.
      if (isset($parameters) && !is_array($parameters)) {
        $results[$index] = NULL;
        $this->logger->error('Invalid parameters when requesting @url: @parameters', [
          '@url' => $api_url . '/' . $query['resource'],
          '@parameters' => print_r($parameters, TRUE) . ' (' . gettype($parameters) . ')',
        ]);
        continue;
      }
      try {
        $promises[$index] = $this->httpClient->getAsync($url, [
          'query' => $parameters,
          'headers' => [
            'Accept' => 'application/json',
            'API-KEY' => $api_key,
            'APP-NAME' => $appname,
          ],
          'timeout' => $timeout,
          'connect_timeout' => $timeout,
          'verify' => $verify_ssl,
        ]);
      }
      catch (\Exception $exception) {
        $this->logger->error('Exception while querying @url with @parameters: @exception', [
          '@url' => $api_url . '/' . $query['resource'],
          '@parameters' => print_r($parameters, TRUE),
          '@exception' => $exception->getMessage(),
        ]);
      }
    }

    // Execute the requests in parallel and retrieve and cache the response's
    // data.
    $promise_results = Utils::settle($promises)->wait();
    foreach ($promise_results as $index => $result) {
      $data = NULL;

      // Parse the response in case of success.
      if ($result['state'] === 'fulfilled') {
        $response = $result['value'];

        // Retrieve the raw response's data.
        if ($response->getStatusCode() === 200) {
          $data = (string) $response->getBody();
        }
        else {
          $this->logger->notice('Unable to retrieve API data (code: @code) when requesting @url with parameters @parameters', [
            '@code' => $response->getStatusCode(),
            '@url' => $api_url . '/' . $queries[$index]['resource'],
            '@parameters' => print_r($queries[$index]['parameters'], TRUE),
          ]);
          $data = '';
        }
      }
      // Otherwise log the error.
      else {
        $this->logger->notice('Unable to retrieve API data (code: @code) when requesting @url with parameters @parameters: @reason', [
          '@code' => $result['reason']->getCode(),
          '@url' => $api_url . '/' . $queries[$index]['resource'],
          '@parameters' => print_r($queries[$index]['parameters'], TRUE),
          '@reason' => $result['reason']->getMessage(),
        ]);
      }

      // Cache the data unless cache is disabled or there was an issue with the
      // request in which case $data is NULL.
      if (isset($cache, $cache_ids[$index], $queries[$index]['resource'])) {
        $tags = static::getCacheTags($queries[$index]['resource']);
        $cache_expiration = $this->time->getRequestTime() + $cache_lifetime;
        $this->cache->set($cache_ids[$index], $data, $cache_expiration, $tags);
      }

      $results[$index] = $data;
    }

    // We don't store the decoded data. This is to ensure that we can use the
    // same cached data regardless of whether to return JSON data or not.
    if ($decode) {
      foreach ($results as $index => $data) {
        if (!empty($data)) {
          // Decode the data, skip if invalid.
          try {
            $data = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);
          }
          catch (\Exception $exception) {
            $data = NULL;
            $this->logger->notice('Unable to decode OCHA Key Figures API data for request @url with parameters @parameters', [
              '@url' => $api_url . '/' . $queries[$index]['resource'],
              '@parameters' => print_r($queries[$index]['parameters'], TRUE),
            ]);
          }

          // Add the resulting data with same index as the query.
          $results[$index] = $data;
        }
      }
    }
    return $results;
  }

  /**
   * Sanitize and simplify an API query parameters.
   *
   * @param array $parameters
   *   API query parameters.
   *
   * @return array
   *   Sanitized parameters.
   */
  public static function sanitizeParameters(array $parameters) {
    $allowed_parameters = [
      'iso3',
      'year',
      'archived',
      'order',
    ];

    // Ensure only allowed parameters are passed and in a fixed order to
    // ease caching of the query results.
    $sanitized_parameters = [];
    if (!empty($parameters)) {
      foreach ($allowed_parameters as $parameter) {
        if (isset($parameters[$parameter])) {
          $sanitized_parameters[$parameter] = $parameters[$parameter];
        }
      }
    }

    return $sanitized_parameters;
  }

  /**
   * Determine the cache id of an API query.
   *
   * @param string $resource
   *   API resource.
   * @param array|string|null $parameters
   *   API parameters.
   *
   * @return string
   *   Cache id.
   */
  public static function getCacheId($resource, $parameters) {
    $hash = hash('sha256', serialize($parameters ?? ''));
    return 'ocha_key_figures_api:queries:' . $resource . ':' . $hash;
  }

  /**
   * Determine the cache tags of an API query's resource.
   *
   * @param string $resource
   *   API resource.
   *
   * @return array
   *   Cache tags.
   */
  public static function getCacheTags($resource) {
    // @todo review what tags would make sense.
    $tags = static::$cacheTags[$resource] ?? [];
    $tags[] = 'ocha_key_figures_api:' . $resource;
    return $tags;
  }

}
