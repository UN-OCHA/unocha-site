<?php

namespace Drupal\unocha_reliefweb\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service class to convert a ReliefWeb River URL into an API payload.
 *
 * Internally, this simply calls the ReliefWeb API conversion endpoint.
 */
class ReliefWebApiConverter {

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
    $this->config = $config_factory->get('unocha_reliefweb.settings');
    $this->time = $time;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('unocha_reliefweb');
  }

  /**
   * Get the API payload for the given ReliefWeb river URL.
   *
   * @param string $url
   *   ReliefWeb River URL.
   * @param int $timeout
   *   Request timeout.
   * @param bool $cache_enabled
   *   Whether to cache the queries or not.
   *
   * @return array
   *   ReliefWeb API payload.
   */
  public function getApiPayload($url, $timeout = 5, $cache_enabled = TRUE) {
    $converter_url = $this->config->get('reliefweb_api_converter');
    if (empty($converter_url) || !is_string($converter_url)) {
      $this->logger->error('Missing or invalid ReliefWeb API converter setting');
      return [];
    }

    $cache_enabled = $cache_enabled && ($this->config->get('reliefweb_api_cache_enabled') ?? TRUE);
    $cache_lifetime = $this->config->get('reliefweb_api_cache_lifetime') ?? 300;
    $verify_ssl = $this->config->get('reliefweb_api_verify_ssl');

    if ($cache_enabled) {
      // Retrieve the cache id for the query.
      $cache_id = static::getCacheId($url);
      // Attempt to retrieve the cached data for the query.
      $cache = $this->cache->get($cache_id);
      if (isset($cache->data)) {
        return $cache->data;
      }
    }

    $data = NULL;
    try {
      $response = $this->httpClient->get($converter_url, [
        'query' => [
          'appname' => $this->config->get('reliefweb_api_appname') ?? 'unocha.org',
          'search-url' => $url,
        ],
        'timeout' => $timeout,
        'connect_timeout' => $timeout,
        'verify' => $verify_ssl,
      ]);
    }
    catch (\Exception $exception) {
      // @todo handle timeouts and skip caching the result in that case?
      $this->logger->error('Exception while requesting the ReliefWeb API converter with @url: @exception', [
        '@url' => $url,
        '@exception' => $exception->getMessage(),
      ]);
      $response = NULL;
    }

    $payload = [];
    if (isset($response)) {
      // Decode the JSON response.
      if ($response->getStatusCode() === 200) {
        $body = (string) $response->getBody();
        if (!empty($body)) {
          // Decode the data, skip if invalid.
          try {
            $data = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
          }
          catch (\Exception $exception) {
            $data = NULL;
            $this->logger->error('Unable to decode ReliefWeb API conversion data for @url', [
              '@url' => $url,
            ]);
          }
        }
      }
      else {
        $this->logger->notice('Unable to retrieve ReliefWeb API conversion data for @url - response code: @code', [
          '@url' => $url,
          '@code' => $response->getStatusCode(),
        ]);
      }

      // Parse the data.
      if (!empty($data['output']['requests']['post']['payload'])) {
        $payload = $data['output']['requests']['post']['payload'];
      }
    }

    if ($cache_enabled) {
      $tags = static::getCacheTags();
      $cache_expiration = $this->time->getRequestTime() + $cache_lifetime;
      $this->cache->set($cache_id, $payload, $cache_expiration, $tags);
    }

    return $payload;
  }

  /**
   * Determine the cache id for the river URL to convert.
   *
   * @param string $url
   *   ReliefWeb river URL to convert.
   *
   * @return string
   *   Cache id.
   */
  public static function getCacheId($url) {
    $hash = hash('sha256', $url);
    return 'reliefweb_api:conversions:' . $hash;
  }

  /**
   * Determine the cache tags of an API query's resource.
   *
   * @return array
   *   Cache tags.
   */
  public static function getCacheTags() {
    $tags[] = 'reliefweb_api:conversions';
    return $tags;
  }

}
