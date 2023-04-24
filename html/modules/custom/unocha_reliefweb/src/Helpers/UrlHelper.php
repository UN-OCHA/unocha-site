<?php

namespace Drupal\unocha_reliefweb\Helpers;

use Drupal\unocha_utility\Helpers\UrlHelper as UtilityUrlHelper;

/**
 * Helper extending the Utility URL helper with additional functionalities.
 */
class UrlHelper extends UtilityUrlHelper {

  /**
   * Get the ReliefWeb URL alias from the given UNOCHA URL.
   *
   * @param string $url
   *   UNOCHA URL. If empty use the current URL.
   *   Ex: https://www.unocha.org/publications/report/france/report-title.
   * @param string $path
   *   White-label path for the ReliefWeb documents on UNOCHA.
   *
   * @return string
   *   A ReliefWeb URL alias based on the given URL.
   *   Ex: https://reliefweb.int/report/france/report-title.
   */
  public static function getReliefWebUrlFromUnochaUrl($url = '', $path = 'publications') {
    $url = $url ?: \Drupal::request()->getRequestUri();
    $base_url = \Drupal::config('unocha_reliefweb.settings')->get('reliefweb_website') ?? 'https://reliefweb.int';
    return preg_replace('#(https://[^/]+)?/' . $path . '/#', $base_url . '/', $url);
  }

  /**
   * Get a UNOCHA URL from a ReliefWeb URL alias.
   *
   * @param string $alias
   *   ReliefWeb URL alias.
   *   Ex: https://reliefweb.int/report/france/report-title.
   * @param string $path
   *   White-label path for the ReliefWeb documents on UNOCHA.
   *
   * @return string
   *   A UNOCHA URL based on the given URL alias.
   *   Ex: https://www.unocha.org/publications/report/france/report-title.
   */
  public static function getUnochaUrlFromReliefWebUrl($alias, $path = 'publications') {
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    return preg_replace('#^https?://[^/]+/#', $base_url . '/' . $path . '/', $alias);
  }

}
