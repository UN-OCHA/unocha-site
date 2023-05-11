<?php

namespace Drupal\unocha_canto\Plugin\Field\FieldWidget;

use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;

/**
 * Plugin implementation of the 'canto_media_library' widget.
 *
 * @FieldWidget(
 *   id = "canto_media_library",
 *   label = @Translation("Canto media library"),
 *   description = @Translation("Allows you to select items from the media library or Canto."),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE,
 * )
 *
 * @internal
 *   Plugin classes are internal.
 */
class CantoMediaLibrary extends MediaLibraryWidget {

}
