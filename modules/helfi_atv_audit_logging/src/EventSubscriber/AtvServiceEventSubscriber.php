<?php

namespace Drupal\helfi_atv_audit_logging\EventSubscriber;

use Drupal\helfi_atv\Event\AtvServiceExceptionEvent;
use Drupal\helfi_atv\Event\AtvServiceOperationEvent;
use Drupal\helfi_audit_log\AuditLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Monitors submission view events and logs them to audit log.
 */
class AtvServiceEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private AuditLogService $auditLogService
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AtvServiceExceptionEvent::EVENT_ID][] = ['onException'];
    $events[AtvServiceOperationEvent::EVENT_ID][] = ['onOperation'];
    return $events;
  }

  /**
   * Audit log the exception.
   *
   * @param \Drupal\helfi_atv\Event\AtvServiceExceptionEvent $event
   *   An exception event.
   */
  public function onException(AtvServiceExceptionEvent $event) {
    $exception = $event->getException();
    $message = [
      'operation' => 'ATV_QUERY',
      'status' => 'EXCEPTION',
      'target' => [
        'name' => $exception->getMessage(),
        'type' => get_class($exception),
      ],
    ];

    $this->auditLogService->dispatchEvent($message);
  }

  /**
   * Audit log the operation.
   *
   * @param \Drupal\helfi_atv\Event\AtvServiceEOperationEvent $event
   *   An operation event.
   */
  public function onOperation(AtvServiceOperationEvent $event) {
    $method = $event->getMethod();
    $url = $event->getUrl();
    $message = [
      'operation' => 'ATV_QUERY',
      'status' => 'SUCCESS',
      'target' => [
        'name' => $url,
        'type' => $method,
      ],
    ];

    $this->auditLogService->dispatchEvent($message);
  }

}
