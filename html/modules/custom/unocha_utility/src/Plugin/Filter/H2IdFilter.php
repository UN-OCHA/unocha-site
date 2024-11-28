<?php

namespace Drupal\unocha_utility\Plugin\Filter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to add id attribute to H2 tags.
 *
 * @Filter(
 *   id = "unocha_h2_id_filter",
 *   title = @Translation("Add Id attribute to H2 tags."),
 *   description = @Translation("Add Id attribute to H2 tags."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class H2IdFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $lib_needed = FALSE;
    if (is_string($text) || $text instanceof MarkupInterface) {
      $html = trim($text);
      if ($html !== '') {
        // Adding this meta tag is necessary to tell \DOMDocument we are dealing
        // with UTF-8 encoded html.
        $flags = LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING;
        $meta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $prefix = '<!DOCTYPE html><html><head>' . $meta . '</head><body>';
        $suffix = '</body></html>';
        $dom = new \DOMDocument();
        $dom->loadHTML($prefix . $text . $suffix, $flags);

        // Process the links.
        $h2s = $dom->getElementsByTagName('h2');
        foreach ($h2s as $h2) {
          $lib_needed = TRUE;

          /** @var \DOMElement $h2 */
          $id = $h2->getAttribute('id');
          if (empty($id)) {
            $id = Html::cleanCssIdentifier(strtolower($h2->textContent));
            $h2->setAttribute('id', $id);
          }

          $h2->setAttribute('data-ocha-h2-link', $id);
          $a = $dom->createElement('a', '#');
          $a->setAttribute('data-ocha-h2-link-tag', $id);
          $a->setAttribute('href', '#' . $id);
          $h2->prepend($a);
        }

        // Get the modified html.
        $html = $dom->saveHTML();

        // Search for the body tag and return its content.
        $start = mb_strpos($html, '<body>');
        $end = mb_strrpos($html, '</body>');
        if ($start !== FALSE && $end !== FALSE) {
          $start += 6;
          $text = trim(mb_substr($html, $start, $end - $start));
        }
      }
    }

    $result = new FilterProcessResult($text);
    if ($lib_needed) {
      $result->setAttachments([
        'library' => [
          'unocha_utility/h2-id-link',
        ],
      ]);
    }

    return $result;
  }

}
