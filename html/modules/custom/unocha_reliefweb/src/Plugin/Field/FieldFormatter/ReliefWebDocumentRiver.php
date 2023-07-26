<?php

namespace Drupal\unocha_reliefweb\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'reliefweb_document_river' formatter.
 *
 * @FieldFormatter(
 *   id = "reliefweb_document_river",
 *   label = @Translation("ReliefWeb Document River"),
 *   field_types = {
 *     "reliefweb_document"
 *   }
 * )
 */
class ReliefWebDocumentRiver extends ReliefWebRiver {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $white_label = !empty($this->getSetting('white_label'));
    $paginated = !empty($this->getSetting('paginated'));
    $river = $this->getFieldSetting('river');

    // Retrieve the limit from a potential river limit field on the parent
    // entity.
    $entity = $items->getEntity();
    if ($entity->hasField('field_limit')) {
      $limit = $entity->field_limit?->value;
    }
    $limit = $limit ?? 5;

    // Get the list of RW document URLs.
    $urls = [];
    /** @var \Drupal\unocha_reliefweb\Plugin\Field\FieldType\ReliefWebRiver $item */
    foreach ($items as $item) {
      $url = $item->getUrl();
      if (!empty($url)) {
        $urls[] = $url;
      }
    }
    if (empty($urls)) {
      return [];
    }

    $pager_id = NULL;
    if ($paginated) {
      $pager_id = $this->pagerManager->getMaxPagerElementId() + 1;
    }

    $offset = (isset($pager_id) ? $this->pagerManager->findPage($pager_id) : 0) * $limit;

    $data = $this->getReliefWebDocuments()->getRiverDataFromDocumentUrls($river, $urls, $limit, $offset, NULL, $white_label);
    if (empty($data['entities'])) {
      return [];
    }

    $elements = [
      '#theme' => 'unocha_reliefweb_river__' . $this->viewMode,
      '#resource' => $data['river']['resource'],
      '#title' => '',
      '#entities' => $data['entities'],
    ];

    // Initialize the pager.
    if (isset($pager_id) && !empty($data['total'])) {
      $pager = $this->pagerManager->createPager($data['total'], $limit, $pager_id);

      // Add the pager.
      $elements['#pager'] = [
        '#type' => 'pager',
        '#element' => $pager_id,
      ];

      // Add the results.
      $elements['#results'] = $this->getRiverResults($pager, count($data['entities']));
    }

    return $elements;
  }

}
