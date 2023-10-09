<?php

namespace Drupal\helfi_atv_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the http client factory service.
 *
 * Drupal recognizes this automatically due to correct naming.
 */
class HelfiAtvTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override http client factory.
    if ($container->hasDefinition('http_client_factory')) {
      $definition = $container->getDefinition('http_client_factory');
      $definition->setClass('Drupal\helfi_atv_test\MockClientFactory');
      $definition->setArguments([]);
    }
    else {
      throw \Exception('Error in altering services');
    }
    if ($container->hasDefinition('helfi_helsinki_profiili.userdata')) {
      $definition = $container->getDefinition('helfi_helsinki_profiili.userdata');
      $definition->setClass('Drupal\helfi_atv_test\MockHelsinkiProfiiliUserData');
    }
    else {
      throw \Exception('Error in altering services');
    }
  }

}
