<?php

namespace Drupal\helfi_atv\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event submission create.
 */
class AtvServiceExceptionEvent extends Event {

  const EVENT_ID = 'atv_service.exception';

  /**
   * Construct a new event.
   *
   * @param \Exception $exception
   *  The exception
   */
  public function __construct(
    private \Exception $exception,
  ) {}

  /**
   * Get the exception.
   *
   * @return \Exception
   *   The exception.
   */
  public function getException() {
    return $this->exception;
  }

}
