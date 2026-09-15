<?php

/**
 * @file
 * Post update file for the unocha_paragraphs module.
 */

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Implements hook_deploy_NAME().
 *
 * Change the view mode of the stories paragraphs.
 */
function unocha_paragraphs_deploy_stories_paragraph_view_mode(&$sandbox) {
  $paragraphs = \Drupal::entityTypeManager()
    ->getStorage('paragraph')
    ->loadByProperties([
      'type' => 'stories',
    ]);

  foreach ($paragraphs as $paragraph) {
    $view_mode = $paragraph->field_limit->value == 4 ? 'cards_with_featured' : 'cards';
    $paragraph->paragraph_view_mode->value = $view_mode;
    $paragraph->setNewRevision(FALSE);
    $paragraph->save();
  }

  $result = t('Updated view mode of %count stories paragraphs', [
    '%count' => count($paragraphs),
  ]);
  return $result;
}

/**
 * Implements hook_deploy_NAME().
 *
 * Truncate oversized field_node values on stories paragraphs.
 */
function unocha_paragraphs_deploy_trim_stories_featured_nodes(&$sandbox) {
  $max = (int) \Drupal::entityTypeManager()
    ->getStorage('field_config')
    ->load('paragraph.stories.field_node')
    ?->getThirdPartySetting(
      'unocha_paragraphs',
      'max_stored_items',
      24
    ) ?: 24;

  if (!isset($sandbox['paragraph_ids'])) {
    $query = \Drupal::database()->select('paragraph__field_node', 'pfn');
    $query->addField('pfn', 'entity_id');
    $query->condition('pfn.bundle', 'stories');
    $query->addExpression('COUNT(pfn.entity_id)', 'item_count');
    $query->groupBy('pfn.entity_id');
    $query->having('COUNT(pfn.entity_id) > :max', [':max' => $max]);
    $sandbox['paragraph_ids'] = $query->execute()->fetchCol();
    $sandbox['total'] = count($sandbox['paragraph_ids']);
    $sandbox['progress'] = 0;
    $sandbox['updated'] = 0;
  }

  if ($sandbox['total'] === 0) {
    $sandbox['#finished'] = 1;
    return t('No stories paragraphs required truncation.');
  }

  $batch_size = 50;
  $ids = array_slice($sandbox['paragraph_ids'], $sandbox['progress'], $batch_size);
  $paragraphs = Paragraph::loadMultiple($ids);

  foreach ($paragraphs as $paragraph) {
    if (unocha_paragraphs_trim_stories_field_node($paragraph)) {
      $paragraph->setNewRevision(FALSE);
      $paragraph->save();
      $sandbox['updated']++;
    }
  }

  $sandbox['progress'] += count($ids);
  $sandbox['#finished'] = min(1, $sandbox['progress'] / $sandbox['total']);

  if ($sandbox['#finished'] >= 1) {
    return t('Truncated featured stories on @updated of @total stories paragraphs to a maximum of @max.', [
      '@updated' => $sandbox['updated'],
      '@total' => $sandbox['total'],
      '@max' => $max,
    ]);
  }
}
