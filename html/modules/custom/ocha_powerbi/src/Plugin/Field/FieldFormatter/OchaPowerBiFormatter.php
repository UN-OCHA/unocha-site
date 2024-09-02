<?php

namespace Drupal\ocha_powerbi\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementations for 'ocha_powerbi' formatter.
 *
 * @FieldFormatter(
 *   id = "ocha_powerbi",
 *   label = @Translation("OCHA PowerBi formatter"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class OchaPowerBiFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $item */
    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#theme' => 'ocha_powerbi_formatter',
        '#embed_url' => $item->getUrl(),
        '#width' => 1140,
        '#height' => 540,
      ];
    }

    return $element;
  }

}
