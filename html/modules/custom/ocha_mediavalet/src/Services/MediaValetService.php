<?php

namespace Drupal\ocha_mediavalet\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ocha_mediavalet\Api\MediaValetClient;
use Drupal\ocha_mediavalet\Api\MediaValetData;
use GuzzleHttp\ClientInterface;

/**
 * Canto API client service class.
 */
class MediaValetService {

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
   * MediaValet API client.
   *
   * @var \Drupal\ocha_mediavalet\Api\MediaValetClient
   */
  protected $mediavaletClient;

  /**
   * Debug info.
   */
  protected array $debugInfo = [];

  /**
   * Debug timer.
   */
  protected float $debugTime = 0;

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
    StateInterface $state,
  ) {
    $this->cache = $cache_backend;
    $this->config = $config_factory->get('ocha_mediavalet.settings');
    $this->time = $time;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('ocha_mediavalet');
    $this->state = $state;

    $this->mediavaletClient = new MediaValetClient(
      $this->httpClient,
      $this->logger,
      $this->config->get('endpoint_login'),
      $this->config->get('endpoint_api'),
      $this->config->get('username'),
      $this->config->get('password'),
      $this->config->get('subscription_key'),
      $this->config->get('client_id'),
      $this->config->get('secret'),
    );

    // Set access token info from cache.
    $access_token_info = $this->getCache('mediavalet');
    if ($access_token_info) {
      $this->mediavaletClient->setAccessTokenInfo($access_token_info);
    }

    $this->logIt('Instance created');
  }

  /**
   * Get count.
   */
  public function getCount() : int {
    return $this->mediavaletClient->getCount();
  }

  /**
   * Set count.
   */
  public function setCount(int $count) : self {
    $this->mediavaletClient->setCount($count);

    return $this;
  }

  /**
   * Get offset.
   */
  public function getOffset() : int {
    return $this->mediavaletClient->getOffset();
  }

  /**
   * Set offset.
   */
  public function setOffset(int $offset) : self {
    $this->mediavaletClient->setOffset($offset);

    return $this;
  }

  /**
   * Get result info.
   */
  public function getResultInfo() : array {
    return $this->mediavaletClient->getResultInfo();
  }

  /**
   * Log it.
   */
  protected function logIt($text) {
    if (!$this->config->get('debug')) {
      return;
    }

    if ($this->debugTime == 0) {
      $this->debugTime = microtime(TRUE);
    }
    $this->debugInfo[] = [
      'time' => microtime(TRUE),
      'offset' => round(1000 * (microtime(TRUE) - $this->debugTime), 2),
      'message' => $text,
    ];
  }

  /**
   * Get debug log.
   */
  public function getDebugLog() {
    return $this->debugInfo;
  }

  /**
   * Update cached access token.
   */
  protected function updateCachedAccessTokenInfo() {
    $this->cacheIt('mediavalet', $this->mediavaletClient->getAccessTokenInfo());

    if ($this->config->get('debug')) {
      $this->logger->notice('<pre>' . print_r($this->debugInfo, TRUE) . '</pre>');
    }
  }

  /**
   * Cache data.
   */
  protected function cacheIt($cid, $data) {
    $cache_lifetime = $this->config->get('max_age');
    $cache_expiration = $this->time->getRequestTime() + $cache_lifetime;
    $this->cache->set($cid, $data, $cache_expiration);
  }

  /**
   * Get cached data.
   */
  protected function getCache($cid) {
    $cache = $this->cache->get($cid);
    if (isset($cache->data)) {
      return $cache->data;
    }

    return FALSE;
  }

  /**
   * Get categories.
   */
  public function getCategories() : MediaValetData {
    $this->logIt('getCategories called');

    $cid = implode(':', [
      'categories',
      $this->getCount(),
      $this->getOffset(),
    ]);

    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $categories = $this->mediavaletClient->getCategories();

    $this->logIt('getCategories finished');
    $this->cacheIt($cid, $categories);
    $this->updateCachedAccessTokenInfo();

    return $categories;
  }

  /**
   * Get category assets.
   */
  public function getCategoryAssets(string $category_uuid) : MediaValetData {
    $this->logIt('getCategoryAssets called');

    $cid = implode(':', [
      'categories',
      $category_uuid,
      $this->getCount(),
      $this->getOffset(),
    ]);

    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->getCategoryAssets($category_uuid);

    $this->logIt('getCategoryAssets finished');
    $this->cacheIt($cid, $data);
    $this->updateCachedAccessTokenInfo();

    return $data;
  }

  /**
   * Get assets.
   */
  public function getAsset(string $asset_uuid) : MediaValetData {
    $this->logIt('getAsset called');
    $cid = 'asset:' . $asset_uuid;
    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->getAsset($asset_uuid);

    $this->logIt('getAsset finished');
    $this->cacheIt($cid, $data);
    $this->updateCachedAccessTokenInfo();

    return $data;
  }

  /**
   * Get keywords.
   */
  public function getKeywords() : MediaValetData {
    $this->logIt('getKeywords called');

    $cid = implode(':', [
      'keywords',
      $this->getCount(),
      $this->getOffset(),
    ]);

    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->getKeywords();

    $this->logIt('getKeywords finished');
    $this->cacheIt($cid, $data);
    $this->updateCachedAccessTokenInfo();

    return $data;
  }

  /**
   * Search.
   */
  public function search(string $text, string $category_uuid = '') : MediaValetData {
    $this->logIt('search called with ' . $text . ' and ' . $category_uuid);

    $cid = implode(':', [
      'search',
      md5($text),
      $category_uuid,
      $this->getCount(),
      $this->getOffset(),
    ]);

    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $options = [
      'filters' => '(AssetType EQ Image)',
    ];

    if (!empty($category_uuid)) {
      $options['containerFilter'] = "(CategoryIds/ANY(c: c EQ '" . $category_uuid . "'))";
    }

    $data = $this->mediavaletClient->search($text, $options);

    $this->logIt('search finished');
    $this->cacheIt($cid, $data);
    $this->updateCachedAccessTokenInfo();

    return $data;
  }

}
