<?php

namespace Drupal\unocha_reliefweb\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;

/**
 * ReliefWeb API client service class.
 */
class ReliefWebApiClient {

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
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->cache = $cache_backend;
    $this->config = $config_factory->get('unocha_reliefweb.settings');
    $this->time = $time;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('unocha_reliefweb');
  }

  /**
   * Perform a POST request against the ReliefWeb API.
   *
   * @param string $resource
   *   API resource endpoint (ex: reports).
   * @param array $payload
   *   API request payload (with fields, filters, sort etc.)
   * @param bool $decode
   *   Whether to decode (json) the output or not.
   * @param int|null $timeout
   *   Request timeout in seconds, or NULL to use the config timeout value.
   * @param bool $cache_enabled
   *   Whether to cache the queries or not.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   Optional cacheability metadata to merge with. On success, resource and
   *   result cache tags are added and max-age is set to
   *   reliefweb_api_cache_lifetime. On failure, max-age is set to
   *   reliefweb_api_failure_cache_lifetime so upstream page builds back off
   *   briefly without hammering the API on every request.
   * @param string|null $request_id
   *   Optional request ID suffix (e.g. field.river). Prefixed with the
   *   configured request_id_prefix and sent as the request-id URL query
   *   parameter for API log attribution.
   *
   * @return array|string|null
   *   The data from the API response or NULL in case of error.
   */
  public function request(
    $resource,
    array $payload,
    $decode = TRUE,
    ?int $timeout = NULL,
    $cache_enabled = TRUE,
    ?CacheableMetadata $cacheability = NULL,
    ?string $request_id = NULL,
  ) {
    $queries = [
      $resource => [
        'resource' => $resource,
        'payload' => $payload,
        'request_id' => $request_id,
      ],
    ];

    $results = $this->requestMultiple($queries, $decode, $timeout, $cache_enabled, $cacheability);
    return $results[$resource] ?? NULL;
  }

  /**
   * Perform parallel queries to the API.
   *
   * This only deals with POST requests.
   *
   * @param array $queries
   *   List of queries to perform in parallel. Each item is an associative
   *   array with the resource, the query payload, and optionally request_id.
   * @param bool $decode
   *   Whether to decode (json) the output or not.
   * @param int|null $timeout
   *   Request timeout in seconds, or NULL to use the config timeout value.
   * @param bool $cache_enabled
   *   Whether to cache the queries or not.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   Optional cacheability metadata to merge with. On success, resource and
   *   result cache tags are added and max-age is set to
   *   reliefweb_api_cache_lifetime. On failure, max-age is set to
   *   reliefweb_api_failure_cache_lifetime so upstream page builds back off
   *   briefly without hammering the API on every request.
   *
   * @return array
   *   Return array where each item contains the response to the corresponding
   *   query to the API.
   *
   * @see https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests
   */
  public function requestMultiple(
    array $queries,
    $decode = TRUE,
    ?int $timeout = NULL,
    $cache_enabled = TRUE,
    ?CacheableMetadata $cacheability = NULL,
  ) {
    $results = [];
    $api_url = $this->config->get('reliefweb_api_url');
    $appname = $this->config->get('reliefweb_api_appname') ?: 'unocha.org';
    $timeout ??= $this->getTimeout();
    $cache_enabled = $cache_enabled && ($this->config->get('reliefweb_api_cache_enabled') ?? TRUE);
    $cache_lifetime = (int) ($this->config->get('reliefweb_api_cache_lifetime') ?? 300);
    $failure_lifetime = (int) ($this->config->get('reliefweb_api_failure_cache_lifetime') ?? 60);
    $verify_ssl = $this->config->get('reliefweb_api_verify_ssl');

    // Initialize the result array and retrieve the data for the cached queries.
    $cache_ids = [];
    foreach ($queries as $index => $query) {
      $payload = $query['payload'] ?? '';

      // Sanitize the query payload.
      if (is_array($payload)) {
        $payload = static::sanitizePayload($payload);
      }

      // Update the query payload.
      $queries[$index]['payload'] = $payload;

      // Attempt to get the data from the cache.
      // FALSE marks a known failure (negative cache); strings are success
      // bodies.
      $results[$index] = NULL;
      if ($cache_enabled) {
        // Retrieve the cache id for the query.
        $cache_id = static::getCacheId($query['resource'], $payload);
        $cache_ids[$index] = $cache_id;

        // Attempt to retrieve the cached data for the query.
        $cache = $this->cache->get($cache_id);
        if ($cache !== FALSE && array_key_exists('data', (array) $cache)) {
          if (is_string($cache->data)) {
            if ($decode) {
              // Leave NULL on invalid/corrupt entries so the query is
              // refetched.
              $decoded = $this->decodeApiJsonObject($cache->data);
              if ($decoded !== NULL) {
                if (!empty($decoded['data'])) {
                  static::updateApiUrls($decoded['data']);
                }
                $results[$index] = $decoded;
              }
            }
            else {
              $results[$index] = $cache->data;
            }
          }
          elseif ($cache->data === FALSE) {
            // Known failure: skip HTTP until the negative-cache entry expires.
            $results[$index] = FALSE;
          }
        }
      }
    }

    // Prepare the requests.
    $promises = [];
    foreach ($queries as $index => $query) {
      // Skip queries with cached data (success string/array or failure FALSE).
      if (isset($results[$index])) {
        continue;
      }

      $cache_id = $cache_ids[$index] ?? NULL;
      $resource = $query['resource'] ?? '';
      $parameters = [
        'appname' => $appname,
      ];

      // Add request-id so it is never part of the POST body / cache key.
      $request_id = $this->getQueryRequestId($query);
      if ($request_id !== '-') {
        $parameters['request-id'] = $request_id;
      }

      $url = $api_url . '/' . $resource . '?' . http_build_query($parameters);

      // Encode the payload if needed. It may already be an encoded JSON string.
      $payload = $query['payload'] ?? '';
      if (is_array($payload)) {
        $payload = json_encode($payload);

        // Skip the request if something is wrong with the payload.
        if ($payload === FALSE) {
          $results[$index] = FALSE;
          $this->cacheFailure($cache_id, $resource, $failure_lifetime);
          $this->logger->error('[@request_id] Could not encode payload when requesting @url: @payload', [
            '@request_id' => $request_id,
            '@url' => $api_url . '/' . $resource,
            '@payload' => strtr(print_r($query['payload'], TRUE), "\n", ' '),
          ]);
          continue;
        }
      }

      try {
        $promises[$index] = $this->httpClient->requestAsync('POST', $url, [
          'headers' => ['Content-Type' => 'application/json'],
          'body' => $payload,
          'timeout' => $timeout,
          'connect_timeout' => $timeout,
          'verify' => $verify_ssl,
        ]);
      }
      catch (\Exception $exception) {
        $results[$index] = FALSE;
        $this->cacheFailure($cache_id, $resource, $failure_lifetime);
        $this->logger->error('[@request_id] Exception while querying @url: @exception', [
          '@request_id' => $request_id,
          '@url' => $api_url . '/' . $resource,
          '@exception' => $exception->getMessage(),
        ]);
      }
    }

    // Execute the requests in parallel and retrieve and cache the response's
    // data. Cache the undecoded JSON body so decode TRUE/FALSE share one entry;
    // only store bodies that json_decode successfully. Failures may be stored
    // briefly as FALSE (negative cache).
    $promise_results = Utils::settle($promises)->wait();
    foreach ($promise_results as $index => $result) {
      $raw = NULL;
      $cache_id = $cache_ids[$index] ?? NULL;
      $resource = $queries[$index]['resource'] ?? '';
      $request_id = $this->getQueryRequestId($queries[$index]);

      // Parse the response in case of success.
      if ($result['state'] === 'fulfilled') {
        $response = $result['value'];

        // Retrieve the raw response's data.
        if ($response->getStatusCode() === 200) {
          $raw = (string) $response->getBody();
        }
        else {
          $this->logger->notice('[@request_id] Unable to retrieve API data (code: @code) when requesting @url with payload @payload', [
            '@request_id' => $request_id,
            '@code' => $response->getStatusCode(),
            '@url' => $api_url . '/' . $resource,
            '@payload' => strtr(print_r($queries[$index]['payload'], TRUE), "\n", ' '),
          ]);
        }
      }
      // Otherwise log the error.
      else {
        $this->logger->notice('[@request_id] Unable to retrieve API data (@code: @reason) when requesting @url with payload @payload', [
          '@request_id' => $request_id,
          '@code' => $result['reason']->getCode(),
          '@reason' => $result['reason']->getMessage(),
          '@url' => $api_url . '/' . $resource,
          '@payload' => strtr(print_r($queries[$index]['payload'], TRUE), "\n", ' '),
        ]);
      }

      if ($raw === NULL || $raw === '') {
        $results[$index] = FALSE;
        $this->cacheFailure($cache_id, $resource, $failure_lifetime);
        continue;
      }

      $decoded = $this->decodeApiJsonObject($raw);
      if ($decoded === NULL) {
        $this->logger->notice('[@request_id] Unable to decode ReliefWeb API data for request @url with payload @payload', [
          '@request_id' => $request_id,
          '@url' => $api_url . '/' . $resource,
          '@payload' => strtr(print_r($queries[$index]['payload'], TRUE), "\n", ' '),
        ]);
        $results[$index] = FALSE;
        $this->cacheFailure($cache_id, $resource, $failure_lifetime);
        continue;
      }

      // Cache valid JSON object bodies only.
      if ($cache_id !== NULL) {
        $tags = static::getCacheTags($resource);
        $tags = array_merge($tags, static::buildCacheTagsForResult($decoded, $resource));
        $cache_expiration = $this->time->getRequestTime() + $cache_lifetime;
        $this->cache->set($cache_id, $raw, $cache_expiration, $tags);
      }

      if ($decode) {
        if (!empty($decoded['data'])) {
          static::updateApiUrls($decoded['data']);
        }
        $results[$index] = $decoded;
      }
      else {
        $results[$index] = $raw;
      }
    }

    // Merge resource/result cache tags and max-age so callers can apply this
    // to their render arrays without re-checking. Set max-age once after the
    // loop so a later success cannot overwrite an earlier failure's max-age.
    if ($cacheability !== NULL) {
      $failed = FALSE;
      foreach ($queries as $index => $query) {
        if (!empty($query['resource'])) {
          $cacheability->addCacheTags(static::getCacheTags($query['resource']));
        }
        // Failures are NULL (unset) or FALSE (negative-cache / just failed).
        if (!isset($results[$index]) || $results[$index] === FALSE) {
          $failed = TRUE;
        }
        else {
          $cacheability->addCacheTags(static::buildCacheTagsForResult(
            $results[$index],
            $query['resource'],
          ));
        }
      }
      $cacheability->setCacheMaxAge($failed ? $failure_lifetime : $cache_lifetime);
    }

    // Callers expect NULL on failure, not the internal FALSE marker.
    foreach ($results as $index => $result) {
      if ($result === FALSE) {
        $results[$index] = NULL;
      }
    }

    return $results;
  }

  /**
   * Sanitize and simplify an API query payload.
   *
   * @param array $payload
   *   API query payload.
   * @param bool $combine
   *   TRUE to optimize the filters by combining their values when possible.
   *
   * @return array
   *   Sanitized payload.
   */
  public static function sanitizePayload(array $payload, $combine = FALSE) {
    // Remove search value and fields if the value is empty.
    if (empty($payload['query']['value'])) {
      unset($payload['query']);
    }
    // Optimize the filter if any.
    if (isset($payload['filter'])) {
      $filter = static::optimizeFilter($payload['filter'], $combine);

      if (!empty($filter)) {
        $payload['filter'] = $filter;
      }
      else {
        unset($payload['filter']);
      }
    }
    // Optimize the facet filters if any.
    if (isset($payload['facets'])) {
      foreach ($payload['facets'] as $key => $facet) {
        if (isset($facet['filter'])) {
          $filter = static::optimizeFilter($facet['filter'], $combine);
          if (!empty($filter)) {
            $payload['facets'][$key]['filter'] = $filter;
          }
          else {
            unset($payload['facets'][$key]['filter']);
          }
        }
      }
    }
    return $payload;
  }

  /**
   * Optimize a filter, removing uncessary nested conditions.
   *
   * @param array $filter
   *   Filter following the API syntax.
   * @param bool $combine
   *   TRUE to optimize even more the filter by combining values when possible.
   *
   * @return array|null
   *   Optimized filter.
   */
  public static function optimizeFilter(array $filter, $combine = FALSE) {
    if (isset($filter['conditions'])) {
      if (isset($filter['operator'])) {
        $filter['operator'] = strtoupper($filter['operator']);
      }

      foreach ($filter['conditions'] as $key => $condition) {
        $condition = static::optimizeFilter($condition, $combine);
        if (isset($condition)) {
          $filter['conditions'][$key] = $condition;
        }
        else {
          unset($filter['conditions'][$key]);
        }
      }
      // @todo eventually check if it's worthy to optimize by combining
      // filters with same field and same negation inside a conditional filter.
      if (!empty($filter['conditions'])) {
        if ($combine) {
          $filter['conditions'] = static::combineConditions($filter['conditions'], $filter['operator'] ?? NULL);
        }
        if (count($filter['conditions']) === 1) {
          $condition = reset($filter['conditions']);
          if (!empty($filter['negate'])) {
            $condition['negate'] = TRUE;
          }
          $filter = $condition;
        }
      }
      else {
        $filter = NULL;
      }
    }
    return !empty($filter) ? $filter : NULL;
  }

  /**
   * Combine simple filter conditions to shorten the filters.
   *
   * @param array $conditions
   *   Filter conditions.
   * @param string $operator
   *   Operator to join the conditions.
   *
   * @return array
   *   Combined and simplied filter conditions.
   */
  public static function combineConditions(array $conditions, $operator = 'AND') {
    $operator = $operator ?: 'AND';
    $filters = [];
    $result = [];

    foreach ($conditions as $condition) {
      $field = $condition['field'] ?? NULL;
      $value = $condition['value'] ?? NULL;
      $condition_operator = $condition['operator'] ?? NULL;

      // Nested conditions - flatten the condition's conditions.
      if (!empty($condition['conditions'])) {
        $condition['conditions'] = static::combineConditions($condition['conditions'], $condition_operator);
        $result[] = $condition;
      }
      // Existence filter - keep as is.
      elseif (is_null($value)) {
        $result[] = $condition;
      }
      // Range filter - keep as is.
      elseif (is_array($value) && (isset($value['from']) || isset($value['to']))) {
        $result[] = $condition;
      }
      // Different operator or negated condition - keep as is.
      elseif ((isset($condition_operator) && $condition_operator !== $operator) || !empty($condition['negate'])) {
        $result[] = $condition;
      }
      elseif (is_array($value)) {
        foreach ($value as $item) {
          $filters[$field][] = $item;
        }
      }
      else {
        $filters[$field][] = $value;
      }
    }

    foreach ($filters as $field => $values) {
      $filter = [
        'field' => $field,
      ];

      $value = array_unique($values);
      if (count($value) === 1) {
        $filter['value'] = reset($value);
      }
      else {
        $filter['value'] = $value;
        $filter['operator'] = $operator;
      }
      $result[] = $filter;
    }
    return $result;
  }

  /**
   * Determine the cache id of an API query.
   *
   * @param string $resource
   *   API resource.
   * @param array|string|null $payload
   *   API payload.
   *
   * @return string
   *   Cache id.
   */
  public static function getCacheId($resource, $payload) {
    $hash = hash('sha256', serialize($payload ?? ''));
    return 'reliefweb_api:queries:' . $resource . ':' . $hash;
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
    $tags[] = 'reliefweb_api:' . $resource;
    return $tags;
  }

  /**
   * Construct cache tags for an API result.
   *
   * @param array|string $data
   *   Decoded API result array, or JSON-encoded API result string.
   * @param string $resource
   *   API resource.
   *
   * @return array
   *   Array of cache tags.
   */
  public static function buildCacheTagsForResult(array|string $data, string $resource) {
    if ($data === '' || $data === []) {
      return [];
    }

    // Map resource to bundle.
    $resource_to_bundle = [
      'updates' => 'report',
      'reports' => 'report',
      'jobs' => 'job',
      'training' => 'training',
      'disasters' => 'disaster',
      'countries' => 'country',
      'themes' => 'theme',
    ];

    $bundle = $resource_to_bundle[$resource] ?? NULL;
    if (!$bundle) {
      return [];
    }

    if (is_string($data)) {
      try {
        $data = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException $exception) {
        return [];
      }
      if (!is_array($data)) {
        return [];
      }
    }

    // Add cache tags for each result.
    $tags = ['reliefweb:' . $bundle];
    if (!empty($data['data'])) {
      foreach ($data['data'] as $item) {
        if (isset($item['id']) && is_numeric($item['id'])) {
          $tags[] = 'reliefweb:' . $bundle . ':' . $item['id'];
        }
      }
    }

    return $tags;
  }

  /**
   * Update the host of API URL fields recursively.
   *
   * Note: this mostly for development to convert the URLs from the API used
   * for dev (ex: stage) to URLs starting with `reliefweb.int`.
   *
   * @param array $data
   *   API data.
   * @param string $replacement
   *   Replacement host and scheme.
   * @param string $pattern
   *   Pattern to replace.
   * @param string $recursive
   *   TRUE to also check subfields.
   */
  public static function updateApiUrls(array &$data, $replacement = 'https://reliefweb.int/', $pattern = '#https?://[^/]+/#', $recursive = TRUE) {
    foreach ($data as $key => $item) {
      if (is_string($item) && strpos($key, 'url') === 0) {
        $data[$key] = preg_replace($pattern, $replacement, $item);
      }
      elseif (is_array($item) && $recursive) {
        static::updateApiUrls($data[$key], $replacement, $pattern, $recursive);
      }
    }
  }

  /**
   * Get the request timeout in seconds.
   *
   * @return int
   *   Timeout from reliefweb_api_timeout, falling back to 5.
   */
  protected function getTimeout(): int {
    $timeout = (int) ($this->config->get('reliefweb_api_timeout') ?? 5);
    return $timeout > 0 ? $timeout : 5;
  }

  /**
   * Get the request-id prefix to use in the API queries.
   *
   * @return string
   *   Request ID prefix.
   */
  protected function getRequestIdPrefix(): string {
    return $this->config->get('reliefweb_api_request_id_prefix') ?: 'unocha';
  }

  /**
   * Get the full request-id for a query, for URL params and log messages.
   *
   * @param array $query
   *   Query definition that may contain a request_id suffix.
   *
   * @return string
   *   Prefixed request-id, or '-' when unset.
   */
  protected function getQueryRequestId(array $query): string {
    if (!empty($query['request_id']) && is_string($query['request_id'])) {
      return $this->getRequestIdPrefix() . '.' . $query['request_id'];
    }
    return '-';
  }

  /**
   * Mark a failed query to avoid immediate retries.
   *
   * @param string|null $cache_id
   *   Cache ID for the query, or NULL when caching is disabled.
   * @param string $resource
   *   API resource for cache tags.
   * @param int $failure_lifetime
   *   How long to remember the failure; zero disables negative caching.
   */
  protected function cacheFailure(?string $cache_id, string $resource, int $failure_lifetime): void {
    if ($cache_id === NULL || $failure_lifetime <= 0) {
      return;
    }
    $tags = $resource !== '' ? static::getCacheTags($resource) : [];
    $this->cache->set(
      $cache_id,
      FALSE,
      $this->time->getRequestTime() + $failure_lifetime,
      $tags,
    );
  }

  /**
   * Decode a ReliefWeb API JSON object body.
   *
   * @param string $raw
   *   Raw response body.
   *
   * @return array|null
   *   Associative array when the body is a JSON object, NULL otherwise.
   */
  protected function decodeApiJsonObject(string $raw): ?array {
    try {
      $decoded = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      return NULL;
    }

    // Callers expect an object payload (data, totalCount, etc.), not a list
    // or scalar. json_decode('{}', TRUE) becomes [] which array_is_list treats
    // as a list and is also rejected, which is fine for this API.
    if (!is_array($decoded) || array_is_list($decoded)) {
      return NULL;
    }

    return $decoded;
  }

}
