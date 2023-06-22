<?php

namespace Drupal\unocha_reliefweb\Services;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a breadcrumb builder for ReliefWeb white labeled documents.
 */
class ReliefWebBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|null
   */
  protected $languageManager;

  /**
   * Constructs the BookBreadcrumbBuilder.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   Ther controller resolver service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(
    RequestStack $request_stack,
    ControllerResolverInterface $controller_resolver,
    LanguageManagerInterface $language_manager
  ) {
    $this->requestStack = $request_stack;
    $this->controllerResolver = $controller_resolver;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() === 'unocha_reliefweb.publications.document';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();

    try {
      // @see 'unocha_reliefweb.publications.document' route.
      $controller = $this->controllerResolver->getController($this->requestStack->getCurrentRequest());
      $class = reset($controller);
      $ocha_product = call_user_func([$class, 'getOchaProduct']);
      $title = call_user_func([$class, 'getPageTitle']);
    }
    catch (\Exception $exception) {
      return $breadcrumb;
    }

    $current_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);

    $url_options = ['language' => $current_language];

    // Homepage.
    $links = [Link::createFromRoute($this->t('Home'), '<front>', [], $url_options)];

    switch ($ocha_product) {
      // Press releases.
      case 'Press Release':
        $links[] = Link::fromTextAndUrl($this->t('Press Releases'), Url::fromUserInput('/press-releases', $url_options));
        break;

      // Latest / Speeches and Statements.
      case 'Statement/Speech':
        $links[] = Link::createFromRoute($this->t('Latest'), '<nolink>', [], $url_options);
        $links[] = Link::fromTextAndUrl($this->t('Speeches and Statements'), Url::fromUserInput('/speeches-and-statements', $url_options));
        break;

      // Latest / Publications.
      default:
        $links[] = Link::createFromRoute($this->t('Latest'), '<nolink>', [], $url_options);
        $links[] = Link::fromTextAndUrl($this->t('Publications'), Url::fromUserInput('/publications', $url_options));
    }

    // Document's title.
    $links[] = Link::createFromRoute($title, '<nolink>', [], $url_options);

    $breadcrumb->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);
    $breadcrumb->addCacheContexts(['url.path']);
    $breadcrumb->setLinks(array_filter($links));
    return $breadcrumb;
  }

}
