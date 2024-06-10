<?php

namespace Drupal\Tests\helfi_atv\Kernel;

use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Psr7\Response;
use League\OpenAPIValidation\PSR7\RequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;


/**
 * Tests AtvService class.
 *
 * @covers \Drupal\helfi_atv\AtvService
 * @group helfi_atv
 */

class RequestTest extends KernelTestBase {


/**
   * Request validator.
   *
   * @var RequestValidator
   */
  protected RequestValidator $validator; 
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
      // Helsinki profiili requires audit log unnecessarily.
    'helfi_audit_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $yamlFile = __DIR__ . '/Asiointitietovaranto.yaml';
    $this->validator = (new ValidatorBuilder)->fromYamlFile($yamlFile)->getRequestValidator();

    $this->installConfig(['helfi_atv']);
    putenv('ATV_API_KEY=fake');
    putenv('ATV_USE_TOKEN_AUTH=true');
    putenv('ATV_TOKEN_NAME=tokenName');
    putenv('ATV_BASE_URL=127.0.0.1');
    putenv('ATV_VERSION=v1');
    putenv('ATV_USE_CACHE=false');
    putenv('APP_ENV=UNIT_TEST');
    putenv('ATV_SERVICE=service');
    putenv('ATV_MAX_PAGES=10');
  }

  /**
   * Test that response matches with OpenApi Spec.
   */
  public function testPostDocumentRequest(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $data = [
      'id' => 'test-id',
      'type' => 'type',
      'service' => [
        'name' => 'serviceName',
      ],
      'status' => [
        'value' => 'DRAFT',
      ],
      'status_histories' => [
        'DRAFT',
      ],
      'transaction_id' => '67e5504410b1426f9247bb680e5fe0c8',
      'business_id' => '1234567-1',
      'tos_function_id' => '67e5504410b1426f9247bb680e5fe0c8',
      'tos_record_id' => '67e5504410b1426f9247bb680e5fe0c8',
      'draft' => TRUE,
      'human_readable_type' => ['humanType'],
      'metadata' => '{"name": "Name", "value": "Value"}',
      'content' => '{"data": "content"}',
      'created_at' => '2024-06-06T13:13:54.247974+03:00',
      'updated_at' => '2024-06-07T13:13:54.247974+03:00',
      'user_id' => 'a67dec08-cc7c-11ec-a4fb-00155dcd8647',
      'locked_after' => '2024-06-08T13:13:54.247974+03:00',
      'deletable' => TRUE,
      'delete_after' => '2075-01-01',
      'document_language' => 'fi',
      'content_schema_url' => 'schemaURL',
    ];
    $mockClientFactory->addResponse(new Response(201, [], json_encode($data)));
    $service->postDocument($service->createDocument($data));
    $request = $mockClientFactory->getRequest();
    try {
        $match = $this->validator->validate($request);
    } catch (\Exception $e) {
        echo $e->getVerboseMessage();
        return;
    }
    $this->assertEquals('post', $match->method());
    $this->assertEquals('/v1/documents/', $match->path());
  }

  /**
   * Test that response matches with OpenApi Spec.
   */
  public function testPatchDocumentRequest(): void {
    // Prepare the test.
    $mockClientFactory = \Drupal::service('http_client_factory');
    $service = \Drupal::service('helfi_atv.atv_service');
    $data = [
      'id' => 'a67dec08cc7c11eca4fb00155dcd8647',
      'type' => 'type',
      'service' => [
        'name' => 'serviceName',
      ],
      'status' => [
        'value' => 'DRAFT',
      ],
      'transaction_id' => '67e5504410b1426f9247bb680e5fe0c8',
      'business_id' => '1234567-1',
      'tos_function_id' => '67e5504410b1426f9247bb680e5fe0c8',
      'tos_record_id' => '67e5504410b1426f9247bb680e5fe0c8',
      'draft' => TRUE,
      'human_readable_type' => ['humanType'],
      'metadata' => '{"name": "Name", "value": "Value"}',
      'content' => '{"data": "content"}',
      'created_at' => '2024-06-06T13:13:54.247974+03:00',
      'updated_at' => '2024-06-07T13:13:54.247974+03:00',
      'user_id' => 'a67dec08-cc7c-11ec-a4fb-00155dcd8647',
      'locked_after' => '2024-06-08T13:13:54.247974+03:00',
      'deletable' => TRUE,
      'delete_after' => '2075-01-01',
      'document_language' => 'fi',
      'content_schema_url' => 'schemaURL',
    ];
    $mockClientFactory->addResponse(new Response(200, [], json_encode($data)));
    $service->patchDocument('a67dec08-cc7c-11ec-a4fb-00155dcd8647', $data);
    $request = $mockClientFactory->getRequest();
    try {
        $match = $this->validator->validate($request);
    } catch (\Exception $e) {
        echo $e->getVerboseMessage();
        return;
    }
    
    $this->assertEquals('patch', $match->method());
    $this->assertEquals('/v1/documents/{id}/', $match->path());
  }

}
