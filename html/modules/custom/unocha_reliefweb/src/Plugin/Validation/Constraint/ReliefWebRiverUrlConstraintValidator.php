<?php

namespace Drupal\unocha_reliefweb\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valid ReliefWeb River URL constraint validator.
 */
class ReliefWebRiverUrlConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $rivers = array_filter($constraint->rivers ?? []);
    $website = $constraint->website ?? 'https://reliefweb.int';
    $skip_if_empty = !empty($constraint->skipIfEmpty);

    if (is_string($value)) {
      $length = mb_strlen($value);

      // Skip the validation if empty.
      if ($length === 0 && $skip_if_empty) {
        return;
      }

      // Check if the URL is a ReliefWeb river URL.
      $url = UrlHelper::parse($value)['path'];
      foreach ($rivers as $river) {
        if ($url === $website . '/' . $river) {
          return;
        }
      }

      $this->context
        ->buildViolation($constraint->mustBeValidRiverUrl)
        ->setParameter('%field', $this->context->getPropertyName())
        ->addViolation();
    }
  }

}
