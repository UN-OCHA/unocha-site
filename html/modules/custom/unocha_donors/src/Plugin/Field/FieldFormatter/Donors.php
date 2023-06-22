<?php

namespace Drupal\unocha_donors\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\ocha_key_figures\Plugin\Field\FieldFormatter\KeyFigureBase;

/**
 * Plugin implementation of the 'unocha_donors' formatter.
 *
 * @FieldFormatter(
 *   id = "unocha_donors",
 *   label = @Translation("Donors"),
 *   field_types = {
 *     "key_figure",
 *     "key_figure_presence",
 *   }
 * )
 */
class Donors extends KeyFigureBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $format = $this->getSetting('format');
    $precision = $this->getSetting('precision');
    $currency_symbol = $this->getSetting('currency_symbol');
    $field_type = $items->getFieldDefinition()->getType();
    $view_mode = $this->viewMode;

    $elements = [];
    foreach ($items as $delta => $item) {
      if ($field_type === 'key_figure') {
        $data = $this->ochaKeyFiguresApiClient->query($item->getFigureProvider(), $item->getFigureId());
      }
      else {
        $path = implode('/', [
          'ocha-presences',
          $item->getFigureOchaPresence(),
          $item->getFigureYear(),
          'figures',
        ]);
        $data = $this->ochaKeyFiguresApiClient->query($item->getFigureProvider(), $path, [
          'figure_id' => $item->getFigureId(),
        ]);
        $data = reset($data);
      }

      $donors = [];
      if (isset($data['donors'])) {
        foreach ($data['donors'] as $donor) {
          if (isset($donor['name'])) {
            $donors[$donor['name']] = $donor;
          }
        }
      }
      elseif (isset($data['value'])) {
        foreach (preg_split('/,\s+/', trim($data['value'])) as $name) {
          $donors[$name]['name'] = $name;
        }
      }

      if (!empty($donors)) {
        $figure_id = $data['figure_id'] ?? 'donors';
        $type = $item->getFigureProvider() . '_' . strtr($figure_id, '-', '_');

        $elements[$delta] = [
          '#theme' => 'unocha_donors_list__' . $type . '__' . $view_mode,
          '#type' => $type,
          '#title' => $item->getFigureLabel() ?: $this->t('Top donors'),
          '#list' => $donors,
          '#format' => $format,
          '#precision' => $precision,
          '#currency_symbol' => $currency_symbol,
          '#cache' => [
            'max-age' => $this->ochaKeyFiguresApiClient->getMaxAge(),
            'tags' => $this->ochaKeyFiguresApiClient->getCacheTags([
              'provider' => $item->getFigureProvider(),
              'id' => $item->getFigureId(),
            ]),
          ],
        ];
      }
    }

    return $elements;
  }

}
