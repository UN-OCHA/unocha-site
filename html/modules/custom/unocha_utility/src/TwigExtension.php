<?php

namespace Drupal\unocha_utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\unocha_utility\Helpers\HtmlSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Custom twig functions.
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('sanitize_html', [$this, 'sanitizeHtml'], [
        'is_safe' => ['html'],
      ]),
      new TwigFilter('dpm', 'dpm'),
      new TwigFilter('values', 'array_values'),
      new TwigFilter('hide_nested_label', [$this, 'hideNestedLabel']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      // May be in core one day...
      // @see https://www.drupal.org/project/drupal/issues/3184316
      new TwigFunction('entity_url', [$this, 'getEntityUrl']),
      new TwigFunction('entity_link', [$this, 'getEntityLink'], [
        'is_safe' => ['html'],
      ]),
    ];
  }

  /**
   * Sanitize an HTML string, removing unallowed tags and attributes.
   *
   * This also attempts to fix the heading hierarchy, at least preventing
   * the use of h1 and h2 in the sanitized content.
   *
   * @param string $html
   *   HTML string to sanitize.
   * @param bool $iframe
   *   Whether to allow iframes or not.
   * @param int $heading_offset
   *   Offset for the conversion of the headings to perserve the hierarchy.
   * @param array $allowed_attributes
   *   List of attributes that should be preserved (ex: data-disaster-map).
   *
   * @return string
   *   Sanitized HTML string.
   */
  public static function sanitizeHtml($html, $iframe = FALSE, $heading_offset = 2, array $allowed_attributes = []) {
    $sanitizer = new HtmlSanitizer($iframe, $heading_offset, $allowed_attributes);
    return $sanitizer->sanitizeHtml((string) $html);
  }

  /**
   * Get an entity's URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param string $rel
   *   The link relationship type, for example: canonical or edit-form.
   * @param array $options
   *   See \Drupal\Core\Routing\UrlGeneratorInterface::generateFromRoute() for
   *   the available options.
   *
   * @return string
   *   The entity URL.
   */
  public static function getEntityUrl(EntityInterface $entity, $rel = 'canonical', array $options = []) {
    return $entity->toUrl($rel, $options)->toString();
  }

  /**
   * Get an entity's Link.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param string|null $text
   *   The link text for the anchor tag as a translated string. If NULL, it will
   *   use the entity's label. Defaults to NULL.
   * @param string $rel
   *   The link relationship type, for example: canonical or edit-form.
   * @param array $options
   *   See \Drupal\Core\Routing\UrlGeneratorInterface::generateFromRoute() for
   *   the available options.
   *
   * @return \Drupal\Core\GeneratedLink
   *   The entity link HTML.
   */
  public static function getEntityLink(EntityInterface $entity, $text = NULL, $rel = 'canonical', array $options = []) {
    return $entity->toLink($text, $rel, $options)->toString();
  }

  /**
   * Hide a label in a nested render array.
   *
   * @param array $element
   *   Form element.
   * @param array $children
   *   List of nested children of the form element.
   */
  public static function hideNestedLabel(array $element, array $children) {
    $parent = &$element;
    $last = count($children) - 1;
    foreach ($children as $index => $child) {
      if (isset($parent[$child])) {
        if ($index === $last) {
          $parent[$child]['#title_display'] = 'invisible';
        }
        else {
          $parent = &$parent[$child];
        }
      }
      else {
        break;
      }
    }
    return $element;
  }

}
