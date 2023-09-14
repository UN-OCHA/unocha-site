<?php

namespace Drupal\unocha_reliefweb\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check that a URL is a valid ReliefWeb Document URL.
 *
 * @Constraint(
 *   id = "ReliefWebDocumentUrl",
 *   label = @Translation("Valid ReliefWeb Document URL", context = "Validation")
 * )
 */
class ReliefWebDocumentUrlConstraint extends Constraint {

  /**
   * URL to the ReliefWeb website.
   *
   * @var string
   */
  public $website;

  /**
   * Whether to skip the validation when the text is empty.
   *
   * @var bool
   */
  public $skipIfEmpty = FALSE;

  /**
   * Error message when the length of the text field is not within the range.
   *
   * @var string
   */
  public $mustBeValidDocumentUrl = 'The %field is not a valid ReliefWeb Document URL such as https://reliefweb.int/report/country/title';

}
