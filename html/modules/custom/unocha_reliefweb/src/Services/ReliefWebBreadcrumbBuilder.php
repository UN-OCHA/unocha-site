<?php

namespace Drupal\unocha_reliefweb\Services;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The breadcrumb manager service.
   *
   * @var \Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface
   */
  protected $breadcrumbManager;

  /**
   * The access aware router service.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouterInterface
   */
  protected $router;

  /**
   * Constructs the BookBreadcrumbBuilder.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   Ther controller resolver service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface $breadcrumb_manager
   *   The breadcrumb manager service.
   * @param \Drupal\Core\Routing\AccessAwareRouterInterface $router
   *   The router service.
   */
  public function __construct(
    RequestStack $request_stack,
    ControllerResolverInterface $controller_resolver,
    LanguageManagerInterface $language_manager,
    StateInterface $state,
    ChainBreadcrumbBuilderInterface $breadcrumb_manager,
    AccessAwareRouterInterface $router
  ) {
    $this->requestStack = $request_stack;
    $this->controllerResolver = $controller_resolver;
    $this->languageManager = $language_manager;
    $this->state = $state;
    $this->breadcrumbManager = $breadcrumb_manager;
    $this->router = $router;
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
    try {
      // @see 'unocha_reliefweb.publications.document' route.
      $controller = $this->controllerResolver->getController($this->requestStack->getCurrentRequest());
      $class = reset($controller);
      $ocha_product = mb_strtolower(call_user_func([$class, 'getOchaProduct']));
      $title = call_user_func([$class, 'getPageTitle']);
    }
    catch (\Exception $exception) {
      return new Breadcrumb();
    }

    $current_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $url_options = ['language' => $current_language];

    $ocha_product_paths = $this->state->get('unocha_reliefweb.breadcrumb.ocha_product_paths', []);

    // Try the parent path defined for the OCHA product.
    if (isset($ocha_product_paths[$ocha_product]['path'])) {
      $breadcrumb = $this->getBreadcrumbForPath($ocha_product_paths[$ocha_product]['path']);
    }

    // Try the default path.
    if (empty($breadcrumb)) {
      $default_path = $this->state->get('unocha_reliefweb.breadcrumb.default_path', '');
      $breadcrumb = $this->getBreadcrumbForPath($default_path);
    }

    // Default to the homepage.
    if (empty($breadcrumb)) {
      $breadcrumb = new Breadcrumb();

      // Homepage.
      $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>', [], $url_options));
    }

    // Document's title.
    $breadcrumb->addLink(Link::createFromRoute($title, '<nolink>', [], $url_options));

    $breadcrumb->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);
    $breadcrumb->addCacheContexts(['url.path']);
    return $breadcrumb;
  }

  /**
   * Get the breadcrumb object for a path.
   *
   * @param string $path
   *   Path.
   *
   * @return \Drupal\Core\Breadcrumb\Breadcrumb
   *   Breadcrumb object.
   */
  protected function getBreadcrumbForPath($path) {
    if (empty($path)) {
      return NULL;
    }
    try {
      $route = $this->router->match($path);
      if (!empty($route)) {
        $route_name = $route['_route'];
        $route_object = $route['_route_object'];
        $parameters = $route_object->getOption('parameters');
        $raw_parameters = $route['_raw_variables']->all();
        $route_match = new RouteMatch($route_name, $route_object, $parameters, $raw_parameters);
        if ($this->breadcrumbManager->applies($route_match)) {
          $breadcrumb = $this->breadcrumbManager->build($route_match);
          // Make sure the last item which corresponds to the path normally,
          // has a proper URL.
          $links = $breadcrumb->getLinks();
          $link = array_pop($links);
          $links[] = $link->setUrl(Url::fromRouteMatch($route_match));
          // Return a new breadcrumb because we cannot alter the links of
          // an existing breadcrumb.
          return (new Breadcrumb())
            ->addCacheableDependency($breadcrumb)
            ->setLinks($links);
        }
      }
      return NULL;
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

}
