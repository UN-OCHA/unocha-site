<?php

declare(strict_types=1);

namespace Drupal\Tests\unocha_reliefweb\Unit\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\unocha_reliefweb\Services\ReliefWebApiClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the ReliefWeb API client caching behavior.
 */
#[CoversClass(ReliefWebApiClient::class)]
#[Group('unocha_reliefweb')]
class ReliefWebApiClientTest extends UnitTestCase {

  /**
   * Cache backend mock.
   */
  protected CacheBackendInterface&MockObject $cacheBackend;

  /**
   * HTTP client mock.
   */
  protected ClientInterface&MockObject $httpClient;

  /**
   * Logger channel mock.
   */
  protected LoggerChannelInterface&MockObject $logger;

  /**
   * API client under test.
   */
  protected ReliefWebApiClient $apiClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cacheBackend = $this->createMock(CacheBackendInterface::class);
    $this->httpClient = $this->createMock(ClientInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static function (string $key) {
      return match ($key) {
        'reliefweb_api_url' => 'https://api.example.com/v1',
        'reliefweb_api_appname' => 'test-app',
        'reliefweb_api_request_id_prefix' => 'unocha',
        'reliefweb_api_verify_ssl' => TRUE,
        'reliefweb_api_cache_enabled' => TRUE,
        'reliefweb_api_cache_lifetime' => 60,
        'reliefweb_api_failure_cache_lifetime' => 60,
        'reliefweb_api_timeout' => 5,
        default => NULL,
      };
    });

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('unocha_reliefweb.settings')->willReturn($config);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_700_000_000);

    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->logger);

    $this->apiClient = new ReliefWebApiClient(
      $this->cacheBackend,
      $config_factory,
      $time,
      $this->httpClient,
      $logger_factory,
    );
  }

  /**
   * Successful API responses are stored in the API cache bin.
   */
  public function testSuccessfulResponseIsCached(): void {
    $body = '{"data":[{"id":1}],"totalCount":1}';

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        $this->stringContains('reliefweb_api:queries:reports:'),
        $body,
        1_700_000_060,
        $this->callback(static function (array $tags): bool {
          return in_array('reliefweb_api:reports', $tags, TRUE)
            && in_array('reliefweb:report', $tags, TRUE)
            && in_array('reliefweb:report:1', $tags, TRUE);
        }),
      );

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->with(
        'POST',
        $this->stringContains('https://api.example.com/v1/reports?'),
        $this->callback(static function (array $options): bool {
          return ($options['timeout'] ?? NULL) === 5
            && ($options['connect_timeout'] ?? NULL) === 5;
        }),
      )
      ->willReturn(Create::promiseFor(new Response(200, [], $body)));

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertSame([
      'data' => [['id' => 1]],
      'totalCount' => 1,
    ], $result);
  }

  /**
   * Non-200 responses are written as a short-lived failure marker.
   */
  public function testNonSuccessResponseIsNotCached(): void {
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        $this->stringContains('reliefweb_api:queries:reports:'),
        FALSE,
        1_700_000_060,
        $this->callback(static function (array $tags): bool {
          return in_array('reliefweb_api:reports', $tags, TRUE);
        }),
      );

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(500, [], 'error')));

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertNull($result);
  }

  /**
   * Rejected promises are written as a short-lived failure marker.
   */
  public function testRejectedRequestIsNotCached(): void {
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        $this->stringContains('reliefweb_api:queries:reports:'),
        FALSE,
        1_700_000_060,
        $this->anything(),
      );

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->willReturn(Create::rejectionFor(new \RuntimeException('timeout', 0)));

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertNull($result);
  }

  /**
   * Cached successful bodies are returned without calling the HTTP client.
   */
  public function testCachedSuccessfulBodyIsReturned(): void {
    $body = '{"data":[{"id":2}],"totalCount":1}';
    $cache_item = (object) [
      'data' => $body,
    ];

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn($cache_item);

    $this->cacheBackend->expects($this->never())
      ->method('set');

    $this->httpClient->expects($this->never())
      ->method('requestAsync');

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertSame([
      'data' => [['id' => 2]],
      'totalCount' => 1,
    ], $result);
  }

  /**
   * Successful requests merge resource and result tags into cacheability.
   */
  public function testCacheabilityReceivesResourceTagsOnSuccess(): void {
    $body = '{"data":[{"id":3}],"totalCount":1}';
    $this->cacheBackend->method('get')->willReturn(FALSE);
    $this->httpClient->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(200, [], $body)));

    $cacheability = new CacheableMetadata();
    $this->apiClient->request('reports', ['limit' => 1], cacheability: $cacheability);

    $this->assertContains('reliefweb_api:reports', $cacheability->getCacheTags());
    $this->assertContains('reliefweb:report', $cacheability->getCacheTags());
    $this->assertContains('reliefweb:report:3', $cacheability->getCacheTags());
    $this->assertSame(60, $cacheability->getCacheMaxAge());
  }

  /**
   * Failed requests set failure-lifetime max-age on cacheability metadata.
   */
  public function testCacheabilitySetsMaxAgeZeroOnFailure(): void {
    $this->cacheBackend->method('get')->willReturn(FALSE);
    $this->httpClient->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(500, [], 'error')));

    $cacheability = new CacheableMetadata();
    $this->apiClient->request('reports', ['limit' => 1], cacheability: $cacheability);

    $this->assertContains('reliefweb_api:reports', $cacheability->getCacheTags());
    $this->assertSame(60, $cacheability->getCacheMaxAge());
  }

  /**
   * Empty 200 bodies are written as a short-lived failure marker.
   */
  public function testEmptyBodyIsNotCached(): void {
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        $this->stringContains('reliefweb_api:queries:reports:'),
        FALSE,
        1_700_000_060,
        $this->anything(),
      );

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(200, [], '')));

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertNull($result);
  }

  /**
   * Non-JSON 200 bodies are written as a short-lived failure marker.
   */
  public function testNonJsonBodyIsNotCached(): void {
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        $this->stringContains('reliefweb_api:queries:reports:'),
        FALSE,
        1_700_000_060,
        $this->anything(),
      );

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(200, [], ' error ')));

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertNull($result);
  }

  /**
   * JSON array 200 bodies are written as a short-lived failure marker.
   */
  public function testJsonArrayBodyIsNotCached(): void {
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        $this->stringContains('reliefweb_api:queries:reports:'),
        FALSE,
        1_700_000_060,
        $this->anything(),
      );

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(200, [], '[{"id":1}]')));

    $result = $this->apiClient->request('reports', ['limit' => 1]);
    $this->assertNull($result);
  }

  /**
   * Cached failures return NULL and skip the HTTP client.
   */
  public function testCachedFailureMarkerSkipsHttp(): void {
    $cache_item = (object) [
      'data' => FALSE,
    ];

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn($cache_item);

    $this->cacheBackend->expects($this->never())
      ->method('set');

    $this->httpClient->expects($this->never())
      ->method('requestAsync');

    $cacheability = new CacheableMetadata();
    $result = $this->apiClient->request('reports', ['limit' => 1], cacheability: $cacheability);
    $this->assertNull($result);
    $this->assertSame(60, $cacheability->getCacheMaxAge());
  }

  /**
   * Failure lifetime 0 disables negative caching and sets max-age 0.
   */
  public function testFailureLifetimeZeroDoesNotCacheFailures(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static function (string $key) {
      return match ($key) {
        'reliefweb_api_url' => 'https://api.example.com/v1',
        'reliefweb_api_appname' => 'test-app',
        'reliefweb_api_request_id_prefix' => 'unocha',
        'reliefweb_api_verify_ssl' => TRUE,
        'reliefweb_api_cache_enabled' => TRUE,
        'reliefweb_api_cache_lifetime' => 60,
        'reliefweb_api_failure_cache_lifetime' => 0,
        'reliefweb_api_timeout' => 5,
        default => NULL,
      };
    });

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('unocha_reliefweb.settings')->willReturn($config);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_700_000_000);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->logger);

    $api_client = new ReliefWebApiClient(
      $this->cacheBackend,
      $config_factory,
      $time,
      $this->httpClient,
      $logger_factory,
    );

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cacheBackend->expects($this->never())
      ->method('set');

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->willReturn(Create::promiseFor(new Response(500, [], 'error')));

    $cacheability = new CacheableMetadata();
    $result = $api_client->request('reports', ['limit' => 1], cacheability: $cacheability);
    $this->assertNull($result);
    $this->assertSame(0, $cacheability->getCacheMaxAge());
  }

  /**
   * Request ID is appended to the URL query with the configured prefix.
   */
  public function testRequestIdIsAddedToUrl(): void {
    $body = '{"data":[],"totalCount":0}';
    $this->cacheBackend->method('get')->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->with(
        'POST',
        $this->callback(static function (string $url): bool {
          $query = parse_url($url, PHP_URL_QUERY);
          parse_str((string) $query, $parameters);
          return ($parameters['appname'] ?? NULL) === 'test-app'
            && ($parameters['request-id'] ?? NULL) === 'unocha.field.river';
        }),
        $this->anything(),
      )
      ->willReturn(Create::promiseFor(new Response(200, [], $body)));

    $this->apiClient->request(
      'reports',
      ['limit' => 1],
      request_id: 'field.river',
    );
  }

  /**
   * Request ID is omitted from the URL when not provided.
   */
  public function testRequestIdIsOmittedWhenUnset(): void {
    $body = '{"data":[],"totalCount":0}';
    $this->cacheBackend->method('get')->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->with(
        'POST',
        $this->callback(static function (string $url): bool {
          $query = parse_url($url, PHP_URL_QUERY);
          parse_str((string) $query, $parameters);
          return ($parameters['appname'] ?? NULL) === 'test-app'
            && !array_key_exists('request-id', $parameters);
        }),
        $this->anything(),
      )
      ->willReturn(Create::promiseFor(new Response(200, [], $body)));

    $this->apiClient->request('reports', ['limit' => 1]);
  }

  /**
   * Explicit timeout overrides the configured timeout.
   */
  public function testExplicitTimeoutIsUsed(): void {
    $body = '{"data":[],"totalCount":0}';
    $this->cacheBackend->method('get')->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('requestAsync')
      ->with(
        'POST',
        $this->anything(),
        $this->callback(static function (array $options): bool {
          return ($options['timeout'] ?? NULL) === 12
            && ($options['connect_timeout'] ?? NULL) === 12;
        }),
      )
      ->willReturn(Create::promiseFor(new Response(200, [], $body)));

    $this->apiClient->request('reports', ['limit' => 1], timeout: 12);
  }

}
