<?php

namespace Drupal\unocha_utility\Helpers;

use Drupal\Component\Utility\UrlHelper as DrupalUrlHelper;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\Url;

/**
 * Helper extending the Drupal URL helper with additional functionalities.
 */
class UrlHelper extends DrupalUrlHelper {

  /**
   * Encode a URL.
   *
   * This ensures that urls like  '/updates?search=blabli' are properly
   * encoded by decomposing the url and rebuilding it.
   *
   * @param string $url
   *   Url to encode.
   * @param bool $alias
   *   Whether the passed url is already an alias or not. If it's an alias then
   *   this will prevent another lookup.
   *
   * @return string
   *   Encoded url.
   *
   * @todo handle language.
   * @todo review if still need this function and where to add it.
   */
  public static function encodeUrl($url, $alias = TRUE) {
    if (empty($url)) {
      return '';
    }

    if (preg_match('#^(?:(?:[^?]*://)|/)#', $url) !== 1) {
      $url = '/' . $url;
    }
    if (strpos($url, '/') === 0) {
      $url = 'internal:' . $url;
    }
    elseif (strpos($url, '://') === 0) {
      $url = 'http' . $url;
    }

    $parts = static::parse($url);
    return Url::fromUri($parts['path'], [
      'query' => $parts['query'],
      'fragment' => $parts['fragment'],
      'alias' => $alias,
    ])->toString();
  }

  /**
   * Get a path from its alias.
   *
   * @param string $alias
   *   Path alias.
   *
   * @return string
   *   Path.
   */
  public static function getPathFromAlias($alias) {
    return \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  }

  /**
   * Get an alias from its path.
   *
   * @param string $path
   *   Path.
   *
   * @return string
   *   Path alias.
   */
  public static function getAliasFromPath($path) {
    return \Drupal::service('path_alias.manager')->getAliasByPath($path);
  }

  /**
   * Get an absolute file URI.
   *
   * @param string $uri
   *   File URI.
   *
   * @return string
   *   Absolute URI for the file or empty if an error occured.
   */
  public static function getAbsoluteFileUri($uri) {
    if (empty($uri)) {
      return '';
    }
    try {
      return \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
    }
    catch (InvalidStreamWrapperException $exception) {
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isValid($url, $absolute = FALSE) {
    if (empty($url)) {
      return FALSE;
    }
    return parent::isValid($url, $absolute);
  }

}
