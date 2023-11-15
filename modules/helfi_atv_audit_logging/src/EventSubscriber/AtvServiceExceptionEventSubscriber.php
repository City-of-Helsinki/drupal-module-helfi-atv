<?php

namespace Drupal\helfi_atv_audit_logging\EventSubscriber;

use Drupal\helfi_atv\Event\AtvServiceExceptionEvent;
use Drupal\helfi_audit_log\AuditLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Monitors submission view events and logs them to audit log.
 */
class AtvServiceExceptionEventSubscriber implements EventSubscriberInterface {

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
      'operation' => 'EXCEPTION',
      'target' => [
        'message' => $exception->getMessage(),
        'type' => get_class($exception),
        'module' => 'helfi_atv',
      ],
    ];

    $this->auditlogService->dispatchEvent($message);
  }

}
