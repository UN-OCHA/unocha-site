<?php

namespace Drupal\ocha_datawrapper\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Template\Attribute;

/**
 * Plugin implementations for 'ocha_datawrapper' formatter.
 *
 * @FieldFormatter(
 *   id = "ocha_datawrapper",
 *   label = @Translation("OCHA datawrapper formatter"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class OchaDatawrapperFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $attributes = static::extractAttributes($item->value);
      if (static::validateMandatoryAttributes($attributes) === []) {
        $element[$delta] = [
          '#attributes' => new Attribute($attributes),
          '#theme' => 'ocha_datawrapper_formatter',
        ];
      }
    }

    return $element;
  }

  /**
   * Parse the datawrapper embed code for an interactive content.
   *
   * @param string $code
   *   Embed code that should be some HTML string with an iframe.
   *
   * @return array|null
   *   List of extracted attributes or NULL if the embed code doesn't contain
   *   an iframe.
   *
   * @see https://developer.datawrapper.de/docs/custom-embed-code
   */
  public static function extractAttributes($code) {
    if (empty($code)) {
      return NULL;
    }

    $attributes = [];
    $dom = Html::load($code);

    $iframe = $dom->getElementsByTagName('iframe')->item(0);
    if (!isset($iframe)) {
      return NULL;
    }

    // Extract id.
    if ($iframe->hasAttribute('id')) {
      $id = preg_replace('/^datawrapper-chart-/', '', $iframe->getAttribute('id'));
      if (!empty($id)) {
        $attributes['id'] = 'datawrapper-chart-' . $id;
      }
    }

    // Extract url.
    if ($iframe->hasAttribute('src')) {
      $src = $iframe->getAttribute('src');
      if (!empty($id)) {
        $pattern = '~https://datawrapper\.dwcdn\.net/' . preg_quote($id) . '/\d+/$~';
        if (preg_match($pattern, $src) === 1) {
          $attributes['src'] = $src;
        }
      }
    }

    // Extract width.
    if ($iframe->hasAttribute('width')) {
      $width = trim($iframe->getAttribute('width'));
      if (static::validateInt($width)) {
        $attributes['width'] = $width;
      }
    }

    // Extract height.
    if ($iframe->hasAttribute('height')) {
      $height = trim($iframe->getAttribute('height'));
      if (static::validateInt($height)) {
        $attributes['height'] = $height;
      }
    }

    // Extract title.
    if ($iframe->hasAttribute('title')) {
      $title = trim($iframe->getAttribute('title'));
      if (!empty($title)) {
        $attributes['title'] = $title;
      }
    }

    // Extract type.
    if ($iframe->hasAttribute('aria-label')) {
      $aria_label = trim($iframe->getAttribute('aria-label'));
    }
    $attributes['aria-label'] = $aria_label ?? t('Interactive content');

    return $attributes;
  }

  /**
   * Validate that all the mandatory attributes are present.
   *
   * @param array|null $attributes
   *   Attributes to validate.
   *
   * @return array
   *   List of missing mandatory attributes if any.
   */
  public static function validateMandatoryAttributes(?array $attributes) {
    $mandatory = ['id', 'src', 'height', 'title'];
    if (is_null($attributes)) {
      return $mandatory;
    }
    $missing = [];
    foreach ($mandatory as $attribute) {
      if (!isset($attributes[$attribute])) {
        $missing[] = $attribute;
      }
    }
    return $missing;
  }

  /**
   * Validate that a value is a positive integer.
   *
   * @param int $value
   *   Value to check.
   *
   * @return bool
   *   TRUE if the value is a positive integer.
   */
  public static function validateInt($value) {
    return filter_var($value, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 0],
    ]) !== FALSE;
  }

}
