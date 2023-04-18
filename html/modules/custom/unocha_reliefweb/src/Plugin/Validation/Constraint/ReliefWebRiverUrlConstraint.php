<?php

namespace Drupal\unocha_reliefweb\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check that a URL is a valid ReliefWeb River URL.
 *
 * @Constraint(
 *   id = "ReliefWebRiverUrl",
 *   label = @Translation("Valid ReliefWeb River URL", context = "Validation")
 * )
 */
class ReliefWebRiverUrlConstraint extends Constraint {

  /**
   * Allowed rivers.
   *
   * @var array
   */
  public array $rivers;

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
  public $mustBeValidRiverUrl = 'The %field is not a valid ReliefWeb River URL such as https://reliefweb.int/updates';

}
