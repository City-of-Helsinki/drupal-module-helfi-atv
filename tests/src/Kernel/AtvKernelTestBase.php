<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_atv\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for ATV module kernel tests.
 */
abstract class AtvKernelTestBase extends KernelTestBase {


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Drupal.
    'file',
    'user',
    // Contrib.
    'externalauth',
    'openid_connect',
    // Helfi modules.
    'helfi_api_base',
    'helfi_atv',
    'helfi_atv_test',
    'helfi_helsinki_profiili',
    // Helsinki profiili requires audit log unnecessarily.
    'helfi_audit_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['helfi_atv']);

    putenv('ATV_API_KEY=fake');
    putenv('ATV_USE_TOKEN_AUTH=true');
    putenv('ATV_TOKEN_NAME=tokenName');
    putenv('ATV_BASE_URL=127.0.0.1');
    putenv('ATV_VERSION=1.1');
    putenv('ATV_USE_CACHE=false');
    putenv('APP_ENV=UNIT_TEST');
    putenv('ATV_SERVICE=service');
    putenv('ATV_MAX_PAGES=10');
  }

}
