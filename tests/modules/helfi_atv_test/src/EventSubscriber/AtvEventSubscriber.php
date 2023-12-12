<?php

namespace Drupal\helfi_atv_test\EventSubscriber;

use Drupal\helfi_atv\Event\AtvServiceExceptionEvent;
use Drupal\helfi_atv\Event\AtvServiceOperationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Monitors amount of events.
 */
class AtvEventSubscriber implements EventSubscriberInterface {

  /**
   * Count of exception events.
   *
   * @var int
   */
  protected int $exceptionEvents;

  /**
   * Count of operation events.
   *
   * @var int
   */
  protected int $operationEvents;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->exceptionEvents = 0;
    $this->operationEvents = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AtvServiceExceptionEvent::EVENT_ID][] = ['onException'];
    $events[AtvServiceOperationEvent::EVENT_ID][] = ['onOperation'];
    return $events;
  }

  /**
   * Reset counters for a new test.
   */
  public function resetCounters(): void {
    $this->exceptionEvents = 0;
    $this->operationEvents = 0;
  }

  /**
   * Count the exception.
   *
   * @param \Drupal\helfi_atv\Event\AtvServiceExceptionEvent $event
   *   An exception event.
   */
  public function onException(AtvServiceExceptionEvent $event): void {
    $this->exceptionEvents++;
  }

  /**
   * Audit log the operation.
   *
   * @param \Drupal\helfi_atv\Event\AtvServiceEOperationEvent $event
   *   An operation event.
   */
  public function onOperation(AtvServiceOperationEvent $event): void {
    $this->operationEvents++;
  }

  /**
   * Get count of exception events since reset.
   *
   * @return int
   *   Number of events.
   */
  public function getExceptionCount(): int {
    return $this->exceptionEvents;
  }

  /**
   * Get count of operation events since reset.
   *
   * @return int
   *   Number of events.
   */
  public function getOperationCount(): int {
    return $this->operationEvents;
  }

}
