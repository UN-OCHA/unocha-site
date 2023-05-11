<?php

namespace Drupal\unocha_canto\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check that a URL is a valid Canto Portal URL.
 *
 * @Constraint(
 *   id = "CantoPortalUrl",
 *   label = @Translation("Valid Canto Portal URL", context = "Validation")
 * )
 */
class CantoPortalUrlConstraint extends Constraint {

  /**
   * Base URL for the Canto portals.
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
  public $mustBeValidRiverUrl = 'The %field is not a valid Canto Portal ';

}
