<?php

namespace Drupal\ocha_mediavalet\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ocha_mediavalet\Api\MediaValetClient;
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
    $this->logger = $logger_factory->get('unocha_canto');
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
  }

  /**
   * Cache data.
   */
  protected function cacheIt($cid, $data) {
    $cache_lifetime = 300;
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
  public function getCategories() {
    $cid = 'categories';
    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $categories = $this->mediavaletClient->getCategories();
    asort($categories);

    $this->cacheIt($cid, $categories);
    return $categories;
  }

  /**
   * Get category assets.
   */
  public function getCategoryAssets(string $category_uuid) {
    $cid = 'categories:' . $category_uuid;
    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->getCategoryAssets($category_uuid);
    $this->cacheIt($cid, $data);
    return $data;
  }

  /**
   * Get assets.
   */
  public function getAsset(string $asset_uuid) {
    $cid = 'asset:' . $asset_uuid;
    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->getAsset($asset_uuid);
    $this->cacheIt($cid, $data);
    return $data;
  }

  /**
   * Get keywords.
   */
  public function getKeywords() {
    $cid = 'keywords';
    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->getKeywords();
    $this->cacheIt($cid, $data);
    return $data;
  }

  /**
   * Search.
   */
  public function search(string $text) {
    $cid = 'search:' . md5($text);
    $cached = $this->getCache($cid);
    if ($cached) {
      return $cached;
    }

    $data = $this->mediavaletClient->search($text);
    $this->cacheIt($cid, $data);
    return $data;
  }

}
