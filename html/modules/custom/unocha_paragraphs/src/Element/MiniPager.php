<?php

namespace Drupal\unocha_paragraphs\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element for a pager.
 *
 * The pager must be initialized with a call to
 * \Drupal\Core\Pager\PagerManagerInterface::createPager() in order to render
 * properly. When used with database queries, this is performed for you when you
 * extend a select query with \Drupal\Core\Database\Query\PagerSelectExtender.
 *
 * Properties:
 * - #element: (optional, int) The pager ID, to distinguish between multiple
 *   pagers on the same page (defaults to 0).
 * - #pagination_heading_level: (optional) A heading level for the pager.
 * - #parameters: (optional) An associative array of query string parameters to
 *   append to the pager.
 * - #quantity: The maximum number of numbered page links to create (defaults
 *   to 9).
 * - #tags: (optional) An array of labels for the controls in the pages.
 * - #route_name: (optional) The name of the route to be used to build pager
 *   links. Defaults to '<none>', which will make links relative to the current
 *   URL. This makes the page more effectively cacheable.
 *
 * @code
 * $build['pager'] = [
 *   '#type' => 'mini_pager',
 * ];
 * @endcode
 */
#[RenderElement('mini_pager')]
class MiniPager extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        static::class . '::preRenderPager',
      ],
      '#theme' => 'mini_pager',
      // The pager ID, to distinguish between multiple pagers on the same page.
      '#element' => 0,
      // The heading level to use for the pager.
      '#pagination_heading_level' => 'h4',
      // An associative array of query string parameters to append to the pager
      // links.
      '#parameters' => [],
      // The number of pages in the list.
      '#quantity' => 9,
      // An array of labels for the controls in the pager.
      '#tags' => [],
      // The name of the route to be used to build pager links. By default no
      // path is provided, which will make links relative to the current URL.
      // This makes the page more effectively cacheable.
      '#route_name' => '<none>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderPager(array $pager) {
    // Note: the default pager theme process function
    // template_preprocess_pager() also calls
    // \Drupal\Core\Pager\PagerManagerInterface::getUpdatedParameters(), which
    // maintains the existing query string. Therefore
    // template_preprocess_pager() adds the 'url.query_args' cache context,
    // which causes the more specific cache context below to be optimized away.
    // In other themes, however, that may not be the case.
    $pager['#cache']['contexts'][] = 'url.query_args.pagers:' . $pager['#element'];
    return $pager;
  }

}
