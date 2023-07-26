<?php

namespace Drupal\unocha_breadcrumbs\Services;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides a breadcrumb builder for UNOCHA pages.
 */
class UnochaBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|null
   */
  protected $languageManager;

  /**
   * Constructs the BookBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(
    LanguageManagerInterface $language_manager
  ) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    return $node instanceof NodeInterface && in_array($node->bundle(), [
      'story',
      'event',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();

    $node = $route_match->getParameter('node');
    if (empty($node)) {
      return $breadcrumb;
    }

    $current_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);

    if ($node->hasTranslation($current_language->getId())) {
      $node = $node->getTranslation($current_language->getId());
    }

    $url_options = ['language' => $current_language];

    // Homepage.
    $links = [Link::createFromRoute($this->t('Home'), '<front>', [], $url_options)];

    switch ($node->bundle()) {
      // Latest / Events.
      case 'event':
        $links[] = Link::createFromRoute($this->t('Latest'), '<nolink>', [], $url_options);
        $links[] = Link::fromTextAndUrl($this->t('Events'), Url::fromUserInput('/latest/events'));
        break;

      // Latest / News and Stories.
      case 'story':
        $links[] = Link::createFromRoute($this->t('Latest'), '<nolink>', [], $url_options);
        $links[] = Link::fromTextAndUrl($this->t('News and Stories'), Url::fromUserInput('/latest/news-and-stories'));
        break;

      // Skip.
      default:
        return $breadcrumb;
    }

    // Node's title.
    $links[] = Link::createFromRoute($node->label(), '<nolink>', [], $url_options);

    $breadcrumb->addCacheableDependency($node);
    $breadcrumb->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);
    $breadcrumb->addCacheContexts(['url.path']);
    $breadcrumb->setLinks(array_filter($links));
    return $breadcrumb;
  }

}
