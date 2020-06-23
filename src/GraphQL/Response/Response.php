<?php

declare(strict_types = 1);

namespace Drupal\graphql\GraphQL\Response;

use Drupal\jobiqo_graphql\Wrappers\Violation\ViolationCollection;

/**
 * Base class for responses containing the violations.
 */
class Response implements ResponseInterface {

  /**
   * List of violations.
   *
   * @var array
   */
  protected $violations = [];

  /**
   * {@inheritdoc}
   */
  public function addViolation($message, array $properties = []): void {
    $properties['message'] = (string) $message;
    $this->violations[] = $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addViolations(array $messages, array $properties = []): void {
    foreach ($messages as $message) {
      $this->addViolation($message, $properties);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getViolations(): array {
    return $this->violations;
  }

}
