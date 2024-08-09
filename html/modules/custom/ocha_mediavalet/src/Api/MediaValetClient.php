<?php

namespace Drupal\ocha_mediavalet\Api;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * MediaValet API client.
 */
class MediaValetClient {

  const MEDIATYPEIMAGE = 'Image';
  const MEDIATYPEVIDEO = 'Video';

  /**
   * Access token information.
   */
  protected array $accessTokenInfo = [];

  /**
   * Count.
   *
   * @var int
   */
  protected $count = 20;

  /**
   * Offset.
   *
   * @var int
   */
  protected $offset = 0;

  /**
   * Media type.
   *
   * @var string
   */
  protected string $mediaType = self::MEDIATYPEIMAGE;

  /**
   * Result info.
   */
  protected array $resultInfo = [
    'total' => 0,
    'start' => 0,
    'records' => 0,
  ];

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
   * Get count.
   */
  public function getCount() : int {
    return $this->count;
  }

  /**
   * Set count.
   */
  public function setCount(int $count) : self {
    $this->count = $count;

    return $this;
  }

  /**
   * Get offset.
   */
  public function getOffset() : int {
    return $this->offset;
  }

  /**
   * Set offset.
   */
  public function setOffset(int $offset) : self {
    $this->offset = $offset;

    return $this;
  }

  /**
   * Get media type.
   */
  public function getMediaType() : string {
    return $this->mediaType;
  }

  /**
   * Set MediaType.
   */
  public function setMediaType(string $media_type) : self {
    $this->mediaType = $media_type;

    return $this;
  }

  /**
   * Get result info.
   */
  public function getResultInfo() : array {
    return $this->resultInfo;
  }

  /**
   * Set result info.
   */
  protected function setBuildInfo($data) : array {
    return [
      'total' => $data['recordCount']['totalRecordsFound'] ?? 0,
      'start' => $data['recordCount']['startingRecord'] ?? 0,
      'records' => $data['recordCount']['recordsReturned'] ?? 0,
    ];
  }

  /**
   * Get access token info.
   */
  public function getAccessTokenInfo() {
    return $this->accessTokenInfo;
  }

  /**
   * Set access token info.
   */
  public function setAccessTokenInfo(array $access_token_info) {
    $this->accessTokenInfo = $access_token_info;

    return $this;
  }

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

    // Authenticate.
    try {
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
    catch (\Throwable $th) {
      $this->loggerFactory->alert('Access token request failed', [
        'code' => $th->getCode(),
        'message' => $th->getMessage() ?? '',
      ]);
    }

    return FALSE;
  }

  /**
   * Refresh the access token.
   */
  protected function refreshToken() : bool {
    if (empty($this->accessTokenInfo) || !isset($this->accessTokenInfo['refresh_token'])) {
      return FALSE;
    }

    // Refresh token.
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
  public function request($resource, array $payload = [], $method = 'GET', $timeout = 30, $cache_enabled = TRUE) {
    $cid = $this->getCacheId($resource, $method, $payload);
    static $cache = [];

    if ($cache_enabled && isset($cache[$cid])) {
      return $cache[$cid];
    }

    if (!$this->isAccessTokenValid()) {
      return FALSE;
    }

    if ($method == 'GET') {
      $payload += [
        'count' => $this->getCount(),
        'offset' => $this->getOffset(),
      ];
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
  public function getCategories() : MediaValetData {
    $categories = [];
    $data = $this->request('categories');

    foreach ($data['payload'] as $item) {
      $categories[$item['id']] = $item['tree']['name'];
    }

    asort($categories);

    return new MediaValetData(
      $categories,
      $this->setBuildInfo($data),
    );
  }

  /**
   * Get category assets.
   */
  public function getCategoryAssets(string $category_uuid) : MediaValetData {
    $items = [];
    $data = $this->request('categories/' . $category_uuid . '/assets');

    foreach ($data['payload']['assets'] as $item) {
      $items[$item['id']] = [
        'id' => $item['id'],
        'title' => $item['title'],
        'filename' => $item['file']['fileName'],
        'thumb' => $item['media']['thumb'],
        'download' => $item['media']['download'],
        'stream' => $item['media']['streamingManifest'] ?? '',
        'is_image' => strtolower($item['media']['type']) == 'image',
        'is_video' => strtolower($item['media']['type']) == 'video',
      ];
    }

    return new MediaValetData(
      $items,
      $this->setBuildInfo($data),
    );
  }

  /**
   * Get assets.
   */
  public function getAsset(string $asset_uuid) : MediaValetData {
    $item = [];
    $data = $this->request('assets/' . $asset_uuid);

    $item = [
      'id' => $data['payload']['id'],
      'title' => $data['payload']['title'],
      'filename' => $data['payload']['file']['fileName'],
      'thumb' => $data['payload']['media']['thumb'],
      'medium' => $data['payload']['media']['medium'],
      'large' => $data['payload']['media']['large'],
      'original' => $data['payload']['media']['original'],
      'download' => $data['payload']['media']['download'],
      'stream' => $data['payload']['media']['streamingManifest'] ?? '',
      'is_image' => strtolower($data['payload']['media']['type']) == 'image',
      'is_video' => strtolower($data['payload']['media']['type']) == 'video',
      'height' => $data['payload']['file']['imageHeight'] ?? '',
      'width' => $data['payload']['file']['imageWidth'] ?? '',
    ];

    return new MediaValetData(
      $item,
      $this->setBuildInfo($data),
    );
  }

  /**
   * Get keywords.
   */
  public function getKeywords() : MediaValetData {
    $keywords = [];
    $data = $this->request('keywords');

    foreach ($data['payload'] as $item) {
      if ($item['isApproved'] && $item['isSearchable']) {
        $keywords[$item['id']] = $item['keywordName'];
      }
    }

    return new MediaValetData(
      $keywords,
      $this->setBuildInfo($data),
    );
  }

  /**
   * Search.
   */
  public function search($text, array $options = []) : MediaValetData {
    $items = [];

    $options['search'] = $text;
    if (empty($options['filters'])) {
      $options['filters'] = '(AssetType EQ ' . $this->getMediaType() . ')';
    }
    else {
      $options['filters'] = ' AND (AssetType EQ ' . $this->getMediaType() . ')';
    }

    $data = $this->request('assets', $options);

    foreach ($data['payload']['assets'] as $item) {
      $items[$item['id']] = [
        'id' => $item['id'],
        'title' => $item['title'],
        'filename' => $item['file']['fileName'],
        'thumb' => $item['media']['thumb'],
        'download' => $item['media']['download'],
        'stream' => $item['media']['streamingManifest'] ?? '',
        'is_image' => strtolower($item['media']['type']) == 'image',
        'is_video' => strtolower($item['media']['type']) == 'video',
      ];
    }

    return new MediaValetData(
      $items,
      $this->setBuildInfo($data),
    );
  }

}
