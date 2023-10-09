<?php

/**
 * @file
 * Post update file for the unocha_paragraphs module.
 */

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
