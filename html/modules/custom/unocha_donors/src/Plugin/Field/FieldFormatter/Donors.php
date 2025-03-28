<?php

namespace Drupal\unocha_donors\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
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
        $data = $this->ochaKeyFiguresApiClient->getFigureByFigureId(
          $item->getFigureProvider(),
          $item->getFigureCountry(),
          $item->getFigureYear(),
          $item->getFigureId(),
        );
      }
      else {
        $data = $this->ochaKeyFiguresApiClient->getOchaPresenceFigureByFigureId(
          $item->getFigureProvider(),
          $item->getFigureOchaPresence(),
          $item->getFigureYear(),
          $item->getFigureId()
        );
        $data = reset($data);
      }

      $donors = [];
      if (isset($data['donors'])) {
        foreach ($data['donors'] as $donor) {
          if (isset($donor['name'])) {
            $donors[$donor['name']] = $donor;
          }
          // Special case for the OCT top donors.
          elseif (isset($donor['DonorName'])) {
            $donors[$donor['DonorName']] = [
              'name' => $donor['DonorName'],
              'earmarked' => $donor['earmarked'] ?? $donor['Earmarked'] ?? 0,
              'unearmarked' => $donor['unearmarked'] ?? $donor['UnEarmarked'] ?? 0,
              'total' => $donor['total'] ?? $donor['Total'] ?? 0,
            ];
          }
        }
      }
      elseif (isset($data['sectors'])) {
        foreach ($data['sectors'] as $sector) {
          if (isset($sector['name'])) {
            $donors[$sector['name']] = $sector;
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
          '#id' => Html::getUniqueId($type . '-' . $items->getEntity()->id() . '-' . $delta),
          '#type' => $type,
          '#title' => $item->getFigureLabel() ?: $this->t('Top donors'),
          '#year' => $data['year'] ?? NULL,
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
      else {
        if ($view_mode == 'preview') {
          $parameters = [];
          if (!empty($item->getFigureProvider())) {
            $parameters[] = 'Provider: ' . $item->getFigureProvider();
          }
          if (!empty($item->getFigureYear())) {
            $parameters[] = 'Year: ' . $item->getFigureYear();
          }
          if ($field_type === 'key_figure') {
            if (!empty($item->getFigureCountry())) {
              $parameters[] = 'Country: ' . $item->getFigureCountry();
            }
          }
          else {
            if (!empty($item->getFigureOchaPresence())) {
              $parameters[] = 'Presence: ' . $item->getFigureOchaPresence();
            }
          }
          if (!empty($item->getFigureId())) {
            $parameters[] = 'Id: ' . $item->getFigureId();
          }

          $elements[$delta] = [
            'info' => [
              '#markup' => $this->t('No data available'),
            ],
            'parameters' => [
              '#theme' => 'item_list',
              '#items' => $parameters,
            ],
          ];
        }
      }
    }

    return $elements;
  }

}
