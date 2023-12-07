<?php

namespace Drupal\helfi_atv\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event submission create.
 */
class AtvServiceOperationEvent extends Event {

  const EVENT_ID = 'atv_service.operation';

  /**
   * Construct a new event.
   *
   * @param string $method
   *   Method of the operation.
   * @param string $url
   *   URL of the operation.
   */
  public function __construct(
    private string $method,
    private string $url,
  ) {}

  /**
   * Get the the method.
   *
   * @return string
   *   The method.
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   * Get the the url.
   *
   * @return string
   *   The url.
   */
  public function getUrl() {
    return $this->url;
  }

}
