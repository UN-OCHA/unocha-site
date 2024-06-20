<?php

namespace Drupal\ocha_mediavalet\Api;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * MediaValet API client.
 */
class MediaValetClient {

  /**
   * Access token information.
   */
  protected array $accessTokenInfo = [];

  /**
   * Constructor.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $loggerFactory,
    protected string $endpointLogin = 'https://login.mediavalet.com',
    protected string $endpointApi = 'https://api.mediavalet.com',
    protected string $username = '',
    protected string $password = '',
    protected string $subscriptionKey = '',
    protected string $clientId = '',
    protected string $clientSecret = '',
  ) {}

  /**
   * Make sure access token is valid.
   */
  protected function isAccessTokenValid() : bool {
    if (!empty($this->accessTokenInfo) && isset($this->accessTokenInfo['expires']) && $this->accessTokenInfo['expires'] > time()) {
      return TRUE;
    }

    if (!empty($this->accessTokenInfo) && isset($this->accessTokenInfo['refresh_token'])) {
      return $this->refreshToken();
    }

    $endpoint = rtrim($this->endpointLogin, '/') . '/connect/token';
    $hash = base64_encode($this->clientId . ':' . $this->clientSecret);
    $response = $this->httpClient->request('POST', $endpoint, [
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Authorization' => 'Basic ' . $hash,
      ],
      'form_params' => [
        'grant_type' => 'password',
        'username' => $this->username,
        'password' => $this->password,
        'scope' => 'openid api offline_access',
        'state' => 'state-296bc9a0',
        'subscriptionKey' => $this->subscriptionKey,
      ],
    ]);

    if ($response->getStatusCode() != 200) {
      $this->loggerFactory->alert('Access token request failed', [
        'code' => $response->getStatusCode(),
        'message' => $response->getReasonPhrase() ?? '',
      ]);

      return FALSE;
    }

    $access_token_info = json_decode($response->getBody()->getContents(), TRUE);
    $access_token_info['expires'] = time() + $access_token_info['expires_in'];

    $this->accessTokenInfo = $access_token_info;

    return TRUE;
  }

  /**
   * Refresh the access token.
   */
  protected function refreshToken() : bool {
    if (empty($this->accessTokenInfo) || !isset($this->accessTokenInfo['refresh_token'])) {
      return FALSE;
    }

    $endpoint = rtrim($this->endpointLogin, '/') . '/connect/token';
    $response = $this->httpClient->request('POST', $endpoint, [
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'form_params' => [
        'grant_type' => 'refresh_token',
        'refresh_token' => $this->accessTokenInfo,
        'client_id' => $this->clientId,
        'client_secret' => $this->clientSecret,
      ],
    ]);

    if ($response->getStatusCode() != 200) {
      $this->loggerFactory->alert('Refresh token request failed', [
        'code' => $response->getStatusCode(),
        'message' => $response->getReasonPhrase() ?? '',
      ]);

      return FALSE;
    }

    $access_token_info = json_decode($response->getBody()->getContents(), TRUE);
    $access_token_info['expires'] = time() + $access_token_info['expires_in'];

    $this->accessTokenInfo = $access_token_info;

    return TRUE;
  }

  /**
   * Perform a GET request.
   *
   * @param string $resource
   *   API resource endpoint (ex: reports).
   * @param array $payload
   *   API request payload (body for POST or parameters for GET).
   * @param string $method
   *   Request method.
   * @param int $timeout
   *   Request timeout.
   * @param bool $cache_enabled
   *   Whether to cache the queries or not.
   *
   * @return array|null
   *   The data from the API response or NULL in case of error.
   */
  public function request($resource, array $payload = [], $method = 'GET', $timeout = 5, $cache_enabled = TRUE) {
    $cid = $this->getCacheId($resource, $method, $payload);
    static $cache = [];

    if ($cache_enabled && isset($cache[$cid])) {
      return $cache[$cid];
    }

    if (!$this->isAccessTokenValid()) {
      return FALSE;
    }

    $endpoint = rtrim($this->endpointApi, '/') . '/' . ltrim($resource, '/');
    $response = $this->httpClient->request($method, $endpoint, [
      'timeout' => $timeout,
      'connect_timeout' => $timeout,
      'headers' => [
        'x-mv-api-version' => '1.1',
        'Accept' => 'application/json',
        'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        'Authorization' => 'Bearer ' . $this->accessTokenInfo['access_token'],
      ],
      'query' => $payload,
    ]);

    if ($response->getStatusCode() != 200) {
      $this->loggerFactory->alert('Refresh token request failed', [
        'code' => $response->getStatusCode(),
        'message' => $response->getReasonPhrase() ?? '',
      ]);

      return FALSE;
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);

    if ($cache_enabled) {
      $cache[$cid] = $data;
    }

    return $data;
  }

  /**
   * Determine the cache id of an API query.
   *
   * @param string $resource
   *   API resource.
   * @param string $method
   *   Request method.
   * @param array|string|null $payload
   *   API payload.
   *
   * @return string
   *   Cache id.
   */
  public function getCacheId($resource, $method, $payload) {
    $hash = hash('sha256', serialize($payload ?? ''));
    return $resource . ':' . $method . ':' . $hash;
  }

  /**
   * Get categories.
   */
  public function getCategories() {
    $categories = [];
    $data = $this->request('categories');

    foreach ($data['payload'] as $item) {
      $categories[$item['id']] = $item['tree']['name'];
    }

    return $categories;
  }

  /**
   * Get category assets.
   */
  public function getCategoryAssets(string $category_uuid) {
    $items = [];
    $data = $this->request('categories/' . $category_uuid . '/assets');

    foreach ($data['payload']['assets'] as $item) {
      $items[$item['id']] = [
        'title' => $item['title'],
        'thumb' => $item['media']['thumb'],
        'download' => $item['media']['download'],
      ];
    }

    return $items;
  }

}
