<?php

namespace Drupal\unocha_canto\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;

/**
 * Canto API client service class.
 */
class CantoApiClient {

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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    CacheBackendInterface $cache_backend,
    ConfigFactoryInterface $config_factory,
    TimeInterface $time,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    StateInterface $state
  ) {
    $this->cache = $cache_backend;
    $this->config = $config_factory->get('unocha_canto.settings');
    $this->time = $time;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('unocha_canto');
    $this->state = $state;
  }

  /**
   * Perform a GET request against the Canto API.
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
    $api_url = $this->config->get('canto_api_url');
    $token = $this->getAccessToken();
    $cache_enabled = $cache_enabled && ($this->config->get('canto_api_cache_enabled') ?? TRUE);
    $cache_lifetime = $this->config->get('canto_api_cache_lifetime') ?? 300;
    $verify_ssl = $this->config->get('canto_api_verify_ssl');

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
            'Authorization' => 'Bearer ' . $token,
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
            $this->logger->notice('Unable to decode Canto API data for request @url with parameters @parameters', [
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
   * Retrieve the access token.
   *
   * @return string
   *   Access token.
   */
  public function getAccessToken() {
    $request_time = $this->time->getRequestTime();

    // If we already have a valid non expired token, use it.
    $oauth_token = $this->state->get('canto_api_oauth_token', []);
    if (isset($oauth_token['access_token'], $oauth_token['expiration'])) {
      $expiration = $oauth_token['expiration'];
      // Give a margin of one day.
      if ($request_time < $expiration - (24 * 60 * 60)) {
        return $oauth_token['access_token'];
      }
    }

    $app_id = $this->config->get('canto_api_app_id');
    $app_secret = $this->config->get('canto_api_app_secret');
    $verify_ssl = $this->config->get('canto_api_verify_ssl');
    $oauth_domain_url = $this->config->get('canto_oauth_domain_url');
    $timeout = 5;
    $url = $oauth_domain_url . '/oauth/api/oauth2/compatible/token';

    try {
      $response = $this->httpClient->post($url, [
        'query' => [
          'app_id' => $app_id,
          'app_secret' => $app_secret,
          'grant_type' => 'client_credentials',
          'scope' => 'consumer',
        ],
        'timeout' => $timeout,
        'connect_timeout' => $timeout,
        'verify' => $verify_ssl,
      ]);
    }
    catch (\Exception $exception) {
      $this->logger->error('Exception while querying @url to get the access token: @exception', [
        '@url' => $url,
        '@exception' => $exception->getMessage(),
      ]);
      $response = NULL;
    }

    $token = '';
    if (isset($response)) {
      // Retrieve the raw response's data.
      if ($response->getStatusCode() === 200) {
        $data = (string) $response->getBody();
      }
      else {
        $this->logger->notice('Unable to retrieve API data (code: @code) when requesting @url', [
          '@code' => $response->getStatusCode(),
          '@url' => $api_url . '/' . $queries[$index]['resource'],
        ]);
        $data = '';
      }

      if (!empty($data)) {
        // Decode the data, skip if invalid.
        try {
          $oauth_token = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);
        }
        catch (\Exception $exception) {
          $this->logger->notice('Unable to decode Canto API data for request @url', [
            '@url' => $api_url . '/' . $queries[$index]['resource'],
          ]);
          $oauth_token = NULL;
        }
      }

      // Store the oauth token and its expiration date.
      if (isset($oauth_token['access_token'], $oauth_token['expires_in'])) {
        $oauth_token['expiration'] = $request_time + $oauth_token['expires_in'];
        $this->state->set('canto_api_oauth_token', $oauth_token);
        $token = $oauth_token['access_token'];
      }
    }

    return $token;
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
    // @todo add more parameters.
    $allowed_parameters = [
      'sortBy',
      'sortDirection',
      // Tree view.
      'layer',
      // Assets.
      'keyword',
      'tags',
      'tagsLiteral',
      'keywords',
      'approval',
      // Because why not be consistent and only use sortBy...
      'orderBy',
    ];

    // Ensure only allowed parameters are passed and in a fixed order to
    // ease caching of the query results.
    $sanitized_parameters = [];
    if (!empty($parameters)) {
      foreach ($allowed_parameters as $parameter) {
        if (!empty($parameters[$parameter])) {
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
    return 'canto_api:queries:' . $resource . ':' . $hash;
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
    $tags[] = 'canto_api:' . $resource;
    return $tags;
  }

}
