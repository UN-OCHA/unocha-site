<?php

namespace Drupal\unocha_paragraphs\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters nodes by year of authored on.
 *
 * @ViewsFilter("year_filter")
 */
class YearFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('Filters nodes by year of authored on (created).');
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $current_year = date('Y');
    $options = [];

    for ($year = 2023; $year <= $current_year; $year++) {
      $options[$year] = $year;
    }

    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#options' => $options,
      '#default_value' => $current_year,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    if (!empty($this->value[0])) {
      $query->addWhereExpression(0, "EXTRACT(YEAR FROM FROM_UNIXTIME(node_field_data.created)) = :year", [':year' => $this->value[0]]);
    }
  }

}
