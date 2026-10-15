<?php

declare(strict_types=1);

namespace Drupal\Tests\unocha_reliefweb\Unit\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Tests\UnitTestCase;
use Drupal\unocha_reliefweb\EventSubscriber\ReliefWebPageCacheExpiresSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests ReliefWeb page-cache Expires subscriber.
 */
#[CoversClass(ReliefWebPageCacheExpiresSubscriber::class)]
#[Group('unocha_reliefweb')]
class ReliefWebPageCacheExpiresSubscriberTest extends UnitTestCase {

  /**
   * Fixed request time used in tests.
   */
  protected const REQUEST_TIME = 1_700_000_000;

  /**
   * Subscriber under test.
   */
  protected ReliefWebPageCacheExpiresSubscriber $subscriber;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(static::REQUEST_TIME);
    $this->subscriber = new ReliefWebPageCacheExpiresSubscriber($time);
  }

  /**
   * Sets Expires and Cache-Control when a reliefweb_api tag is present.
   */
  public function testSetsExpiresForReliefWebApiTag(): void {
    $response = new CacheableResponse('ok');
    $response->headers->set('Cache-Control', 'public, max-age=900');
    $response->getCacheableMetadata()
      ->addCacheTags(['reliefweb_api:reports'])
      ->setCacheMaxAge(600);

    $this->subscriber->onRespond($this->createResponseEvent($response));

    $this->assertSame(static::REQUEST_TIME + 600, $response->getExpires()->getTimestamp());
    // Symfony normalizes Cache-Control directive order when reading.
    $this->assertSame('max-age=600, public', $response->headers->get('Cache-Control'));
  }

  /**
   * Sets Expires and Cache-Control when a reliefweb entity tag is present.
   */
  public function testSetsExpiresForReliefWebEntityTag(): void {
    $response = new CacheableResponse('ok');
    $response->headers->set('Cache-Control', 'public, max-age=900');
    $response->getCacheableMetadata()
      ->addCacheTags(['reliefweb:report:123'])
      ->setCacheMaxAge(60);

    $this->subscriber->onRespond($this->createResponseEvent($response));

    $this->assertSame(static::REQUEST_TIME + 60, $response->getExpires()->getTimestamp());
    $this->assertSame('max-age=60, public', $response->headers->get('Cache-Control'));
  }

  /**
   * Leaves headers alone when no ReliefWeb tags are present.
   */
  public function testIgnoresResponsesWithoutReliefWebTags(): void {
    $response = new CacheableResponse('ok');
    $response->getCacheableMetadata()
      ->addCacheTags(['node:1'])
      ->setCacheMaxAge(600);
    $response->setExpires(new \DateTime('@' . (static::REQUEST_TIME - 100)));
    $response->headers->set('Cache-Control', 'public, max-age=900');

    $this->subscriber->onRespond($this->createResponseEvent($response));

    $this->assertSame(static::REQUEST_TIME - 100, $response->getExpires()->getTimestamp());
    $this->assertSame('max-age=900, public', $response->headers->get('Cache-Control'));
  }

  /**
   * Leaves headers alone when max-age is permanent.
   */
  public function testIgnoresPermanentMaxAge(): void {
    $response = new CacheableResponse('ok');
    $response->getCacheableMetadata()
      ->addCacheTags(['reliefweb_api:reports'])
      ->setCacheMaxAge(Cache::PERMANENT);
    $response->setExpires(new \DateTime('@' . (static::REQUEST_TIME - 100)));
    $response->headers->set('Cache-Control', 'public, max-age=900');

    $this->subscriber->onRespond($this->createResponseEvent($response));

    $this->assertSame(static::REQUEST_TIME - 100, $response->getExpires()->getTimestamp());
    $this->assertSame('max-age=900, public', $response->headers->get('Cache-Control'));
  }

  /**
   * Leaves non-cacheable responses alone.
   */
  public function testIgnoresNonCacheableResponses(): void {
    $response = new Response('ok');
    $response->setExpires(new \DateTime('@' . (static::REQUEST_TIME - 100)));
    $response->headers->set('Cache-Control', 'public, max-age=900');

    $this->subscriber->onRespond($this->createResponseEvent($response));

    $this->assertSame(static::REQUEST_TIME - 100, $response->getExpires()->getTimestamp());
    $this->assertSame('max-age=900, public', $response->headers->get('Cache-Control'));
  }

  /**
   * Creates a main-request response event for the given response.
   */
  protected function createResponseEvent(Response $response): ResponseEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new ResponseEvent(
      $kernel,
      Request::create('/'),
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );
  }

}
