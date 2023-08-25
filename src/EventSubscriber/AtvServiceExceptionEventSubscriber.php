<?php

namespace Drupal\helfi_atv\EventSubscriber;

use Drupal\helfi_atv\Event\AtvServiceExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Monitors submission view events and logs them to audit log.
 */
class AtvServiceExceptionEventSubscriber implements EventSubscriberInterface {

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

    try {
      // Try to get service, this will throw exception if not found.
      $auditlogService = \Drupal::service('helfi_audit_log.audit_log');
      if ($auditlogService) {
        $exception = $event->getException();
        $message = [
          'operation' => 'EXCEPTION',
          'target' => [
            'message' => $exception->getMessage(),
            'type' => get_class($exception),
            'module' => 'helfi_atv',
          ],
        ];

        $auditlogService->dispatchEvent($message);
      }
    }
    catch (\Exception $e) {
    }
  }

}
