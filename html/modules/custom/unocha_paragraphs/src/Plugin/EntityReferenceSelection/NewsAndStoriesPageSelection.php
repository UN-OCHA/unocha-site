<?php

namespace Drupal\unocha_paragraphs\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Limits node selection to pages containing a news_and_stories paragraph.
 */
#[EntityReferenceSelection(
  id: 'unocha_paragraphs_news_and_stories_pages:node',
  label: new TranslatableMarkup('News and stories landing pages'),
  entity_types: ['node'],
  group: 'unocha_paragraphs_news_and_stories_pages',
  weight: 0,
)]
class NewsAndStoriesPageSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $node_ids = $this->getNewsAndStoriesPageNodeIds();
    if (empty($node_ids)) {
      $query->condition('nid', 0);
    }
    else {
      $query->condition('nid', $node_ids, 'IN');
    }

    return $query;
  }

  /**
   * Gets node IDs of pages containing a news_and_stories paragraph.
   *
   * @return int[]
   *   Node IDs.
   */
  protected function getNewsAndStoriesPageNodeIds(): array {
    $paragraphs = $this->entityTypeManager
      ->getStorage('paragraph')
      ->loadByProperties(['type' => 'news_and_stories']);

    $node_ids = [];
    foreach ($paragraphs as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }
      $parent = unocha_paragraphs_get_paragraph_parent_node($paragraph);
      if ($parent instanceof NodeInterface && $parent->id()) {
        // Skip sample pages.
        if (
          $parent->hasField('samples_status') &&
          !$parent->get('samples_status')->isEmpty() &&
          ((bool) $parent->get('samples_status')->value) === TRUE
        ) {
          continue;
        }

        $node_ids[$parent->id()] = $parent->id();
      }
    }

    return array_values($node_ids);
  }

}
