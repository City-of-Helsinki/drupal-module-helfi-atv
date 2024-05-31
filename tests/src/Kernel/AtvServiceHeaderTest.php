<?php

namespace Drupal\Tests\helfi_atv\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests AtvServiceHeaderTest class.
 *
 * Test that each request has correct auth headers.
 * AtvService caches headers for one Drupal request.
 *
 * @covers \Drupal\helfi_atv\AtvService
 * @group helfi_atv
 */
class AtvServiceHeaderTest extends KernelTestBase {
  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = [
    // Drupal.
    'file',
    'user',
    // Contrib.
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

  /**
   * Assert if we have correct authorization header.
   */
  public function assertTokenHeaders($headers): void {
    $this->assertArrayHasKey('Authorization', $headers);
    $this->assertArrayNotHasKey('X-Api-Key', $headers);
    $expectedToken = 'Bearer tokenFromMockHelsinkiProfiiliUserData';
    $this->assertEquals($expectedToken, $headers['Authorization'][0]);
  }

  /**
   * Assert if we have correct API Key header.
   */
  public function assertApiKeyHeaders($headers): void {
    $this->assertArrayHasKey('X-Api-Key', $headers);
    $this->assertArrayNotHasKey('Authorization', $headers);
    $expectedKey = getenv('ATV_API_KEY');
    $this->assertEquals($expectedKey, $headers['X-Api-Key'][0]);
  }

  /**
   * Test that correct headers are set.
   *
   * GDPR calls use always API Key.
   */
  public function testDeleteGdprDataHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(204));
    $service->deleteGdprData('userid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertApiKeyHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testGetGdprDataHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(200, [], json_encode([])));
    $service->getGdprData('userid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertApiKeyHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testGetUserDocumentsHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(200, [], json_encode([])));
    $service->getUserDocuments('userid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testGetDocumentHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $document = $service->createDocument([]);
    $mockClientFactory->addResponse(new Response(200, [], json_encode(['results' => [$document]])));
    $service->getDocument('documentid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testCheckDocumentExistsByTransactionIdHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(200, [], json_encode([])));
    $service->checkDocumentExistsByTransactionId('transactionid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testPostDocumentHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $data = [
      'id' => 'doc-id',
      'content' => [
        'type' => 'test',
        'emptyArray' => [],
        'arrayKey' [
          'arrayValue' => 'exists',
        ],
      ],
    ];
    $mockClientFactory->addResponse(new Response(201, [], json_encode($data)));
    $service->postDocument($service->createDocument([]));
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testPatchDocumentHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(200, [], json_encode([])));
    $service->patchDocument('documentid', ['transaction_id' => 'test']);
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testDeleteAttachmentByUrlHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(204));
    $service->deleteAttachmentByUrl('url');
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testDeleteDocumentHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(204));
    $service->deleteDocument($service->createDocument([]));
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testDeleteAttachmentHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(204));
    $service->deleteAttachment('documentid', 'attachmentid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testDeleteAttachmentViaIntegrationIdHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(204));
    $service->deleteAttachmentViaIntegrationId('integrationid');
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test that correct headers are set.
   */
  public function testUploadAttachmentHeaders(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $mockClientFactory->addResponse(new Response(200, [], json_encode([])));
    $fileName = __DIR__ . '/uploadAttachment.txt';
    $file = File::create(['uri' => $fileName]);
    $service->uploadAttachment('documentid', $fileName, $file);
    $headers = $mockClientFactory->getHeaders();
    $this->assertTokenHeaders($headers);
  }

  /**
   * Test paging headers.
   */
  public function testResultsPagingHeaders() {
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
    $service->getUserDocuments('test_user');
    // Check headers.
    $headers1 = $mockClientFactory->getHeaders(0);
    $this->assertTokenHeaders($headers1);
    $headers2 = $mockClientFactory->getHeaders(1);
    $this->assertTokenHeaders($headers2);
  }

}
