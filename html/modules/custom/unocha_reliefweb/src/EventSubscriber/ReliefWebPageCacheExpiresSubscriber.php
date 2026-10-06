<?php

namespace Drupal\unocha_reliefweb\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Aligns page-cache and HTTP TTLs for responses that embed ReliefWeb API data.
 *
 * Internal Page Cache ignores render max-age and stores entries as permanent
 * unless the response carries a future Expires header. FinishResponseSubscriber
 * also sets Cache-Control from system.performance, which browsers and CDNs use.
 * Mapping the bubbled ReliefWeb max-age onto both Expires and Cache-Control
 * keeps Drupal page cache and external caches in sync (success and failure
 * lifetimes).
 *
 * @see \Drupal\page_cache\StackMiddleware\PageCache::storeResponse()
 * @see \Drupal\Core\EventSubscriber\FinishResponseSubscriber::setResponseCacheable()
 */
class ReliefWebPageCacheExpiresSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ReliefWebPageCacheExpiresSubscriber.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * Sets Expires and Cache-Control from ReliefWeb cacheability.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onRespond(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $metadata = $response->getCacheableMetadata();
    if (!$this->hasReliefWebCacheTag($metadata->getCacheTags())) {
      return;
    }

    $max_age = $metadata->getCacheMaxAge();
    if ($max_age === Cache::PERMANENT || $max_age <= 0) {
      return;
    }

    // Overwrite FinishResponseSubscriber's past Expires so Page Cache uses a
    // real expire timestamp instead of Cache::PERMANENT.
    $response->setExpires(new \DateTime('@' . ($this->time->getRequestTime() + $max_age)));
    // Align browsers/CDNs with the same TTL (replaces system.performance
    // cache.page.max_age for these responses).
    $response->headers->set('Cache-Control', 'public, max-age=' . $max_age);
  }

  /**
   * Checks whether any cache tag belongs to ReliefWeb API content.
   *
   * @param string[] $tags
   *   Cache tags from the response.
   *
   * @return bool
   *   TRUE if a reliefweb or reliefweb_api tag is present.
   */
  protected function hasReliefWebCacheTag(array $tags): bool {
    foreach ($tags as $tag) {
      if (str_starts_with($tag, 'reliefweb:') || str_starts_with($tag, 'reliefweb_api:')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after FinishResponseSubscriber::onRespond (priority 0), which sets
    // Expires to a past date and Cache-Control from system.performance.
    return [
      KernelEvents::RESPONSE => ['onRespond', -10],
    ];
  }

}
