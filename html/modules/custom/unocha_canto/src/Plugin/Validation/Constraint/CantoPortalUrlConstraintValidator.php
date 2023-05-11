<?php

namespace Drupal\unocha_canto\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valid Canto Portal URL constraint validator.
 *
 * @todo Add a list of valid URL patterns?
 */
class CantoPortalUrlConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $website = $constraint->website ?? '';
    $skip_if_empty = !empty($constraint->skipIfEmpty);

    if (!empty($website) && is_string($value)) {
      $length = mb_strlen($value);

      // Skip the validation if empty.
      if ($length === 0 && $skip_if_empty) {
        return;
      }

      // Check if the URL is a ReliefWeb river URL.
      $url = UrlHelper::parse($value)['path'];
      if (strpos($url, $website . '/') === 0) {
        return;
      }

      $this->context
        ->buildViolation($constraint->mustBeValidDocumentUrl)
        ->setParameter('%field', $this->context->getPropertyName())
        ->addViolation();
    }
  }

}
