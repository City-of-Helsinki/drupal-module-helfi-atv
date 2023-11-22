<?php

namespace Drupal\Tests\helfi_atv\Kernel;

use Drupal\helfi_atv\AtvAuthFailedException;
use Drupal\helfi_atv\AtvService;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;

/**
 * Tests AtvService class.
 *
 * @covers DefaultClass \Drupal\helfi_atva\AtvService
 * @group helfi_atv
 */
class AtvServiceTest extends KernelTestBase {
  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = [
    // Drupal.
    'file',
    // Contrib.
    'openid_connect',
    // Helfi modules.
    'helfi_api_base',
    'helfi_atv',
    'helfi_atv_test',
    'helfi_helsinki_profiili',
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

  /**
   * Test delete requests with gdpr call.
   */
  public function testDeleteRequests(): void {
    $mockClientFactory = \Drupal::service('http_client_factory');

    // Get the service for testing.
    $service = \Drupal::service('helfi_atv.atv_service');
    $this->assertEquals(TRUE, $service instanceof AtvService);

    // Test successful delete call. HTTP code 204.
    $mockClientFactory->addResponse(new Response(204));
    $response = $service->deleteGdprData('userid');
    $this->assertEquals(TRUE, $response);

    // Test delete call with non 204 success code.
    $mockClientFactory->addResponse(new Response(200, [], 'Unexpected 200 in delete'));
    $response2 = $service->deleteGdprData('userid');
    $this->assertEquals(FALSE, $response2);

    // Test delete call with server error response.
    $mockClientFactory->addResponse(new Response(500, [], 'Fake connection error'));
    $this->expectException(ServerException::class);
    $service->deleteGdprData('userid');

  }

  /**
   * Test paging.
   */
  public function testResultsPaging() {
    $mockClientFactory = \Drupal::service('http_client_factory');
    $mockResult1 = [
      'count' => 15,
      'next' => 'path/to/next/patch',
      'results' => [
        'one',
        'two',
        'three',
        'four',
        'five',
        'six',
        'seven',
        'eight',
        'nine',
        'ten',
      ],
    ];
    $mockResult2 = [
      'count' => 15,
      'results' => [
        'eleven',
        'twelve',
        'thirteen',
        'fourteen',
        'fifteen',
      ],
    ];
    $mockClientFactory->addResponse(new Response(200, [], json_encode($mockResult1)));
    $mockClientFactory->addResponse(new Response(200, [], json_encode($mockResult2)));
    $service = \Drupal::service('helfi_atv.atv_service');
    $results = $service->getUserDocuments('test_user');
    $this->assertEquals(15, count($results));
  }

  /**
   * Test setting auth headers via other method calls.
   */
  public function testSetAuthHeaders() {
    $mockClientFactory = \Drupal::service('http_client_factory');
    // Get the service for testing.
    $service = \Drupal::service('helfi_atv.atv_service');
    $this->assertEquals(TRUE, $service instanceof AtvService);

    // Use token authentication without actual token to get an error.
    putenv('ATV_USE_TOKEN_AUTH=true');
    putenv('ATV_TOKEN_NAME=');
    $mockClientFactory->addResponse(new Response(204));
    $this->expectException(AtvAuthFailedException::class);
    // Use method that sets auth headers.
    $service->deleteAttachmentByUrl('url');
  }

}
