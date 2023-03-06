<?php

namespace Drupal\unocha_reliefweb\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class ReliefWebDocumentRoutes {

  /**
   * Provides dynamic routes.
   */
  public function routes() {
    // @todo find a common prefix or something to be able to identify those
    // routes. Maybe define them in the config so we can add more?
    $routes = [];

    // Example route for press releases.
    $routes['unocha_reliefweb.press_releases.document'] = new Route(
      // Path.
      '/press-releases/{country}/{title}',
      // Route defaults.
      [
        '_controller' => '\Drupal\unocha_reliefweb\Controller\ReliefWebDocumentController::getPageContent',
        '_title_callback' => '\Drupal\unocha_reliefweb\Controller\ReliefWebDocumentController::getPageTitle',
        // @todo get that from the configuration as well and maybe use term ID.
        'ocha_product' => 'Press Release',
      ],
      // Route requirements.
      [
        '_permission'  => 'access content',
      ]
    );

    return $routes;
  }

}
