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
 * @covers \Drupal\helfi_atv\AtvService
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
   * Format application number based by the enviroment in old format.
   */
  public static function getApplicationNumberInEnvFormatOldFormat($appParam, $typeId, $serial): string {
    $applicationNumber = 'GRANTS-' . $appParam . '-' . $typeId . '-' . sprintf('%08d', $serial);

    if ($appParam == 'PROD') {
      $applicationNumber = 'GRANTS-' . $typeId . '-' . sprintf('%08d', $serial);
    }

    return $applicationNumber;
  }

  /**
   * Format application number based by the enviroment.
   */
  public static function getApplicationNumberInEnvFormat($appParam, $typeId, $serial): string {
    $applicationNumber = $appParam . '-' .
      str_pad($typeId, 3, '0', STR_PAD_LEFT) . '-' .
      str_pad($serial, 7, '0', STR_PAD_LEFT);

    if ($appParam == 'PROD') {
      $applicationNumber = str_pad($typeId, 3, '0', STR_PAD_LEFT) . '-' .
        str_pad($serial, 7, '0', STR_PAD_LEFT);
    }

    return $applicationNumber;
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
    $eventSubscriber = \Drupal::service('helfi_atv_test.event_subscriber');
    $eventSubscriber->resetCounters();
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
    // Check that module has sent two operation events and no exception ones.
    $this->assertEquals(2, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    $this->assertEquals(15, count($results));
  }

  /**
   * Test cache.
   */
  public function testCache() {
    $eventSubscriber = \Drupal::service('helfi_atv_test.event_subscriber');
    $eventSubscriber->resetCounters();
    $mockClientFactory = \Drupal::service('http_client_factory');
    $mockResult1 = [
      'count' => 2,
      'results' => [
        [
          'transaction_id' => '1234567890123456',
          'id' => 'id-1',
        ],
        [
          'transaction_id' => '1234567890123457',
          'id' => 'id-2',
        ],
      ],
    ];
    $mockResult2 = [
      'count' => 1,
      'results' => [
        [
          'transaction_id' => '1234567890123458',
          'id' => 'id-3',
        ],
      ],
    ];
    $mockClientFactory->addResponse(new Response(200, [], json_encode($mockResult1)));
    $mockClientFactory->addResponse(new Response(200, [], json_encode($mockResult2)));
    $service = \Drupal::service('helfi_atv.atv_service');
    $searchParams = [
      'lookfor' => 'appenv:test,applicant_type:registered_community,',
      'business_id' => '1234567-1',
      'service_name' => 'AvustushakemusIntegraatio',
    ];
    $results = $service->searchDocuments($searchParams, FALSE);
    $this->assertEquals(2, count($results));
    // Check that module has sent one opration event and no exception ones.
    $this->assertEquals(1, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    // We should get results from cache.
    // Order of parameters should not matter.
    $searchParams2 = [
      'service_name' => 'AvustushakemusIntegraatio',
      'business_id' => '1234567-1',
      'lookfor' => 'appenv:test,applicant_type:registered_community,',
    ];
    $results2 = $service->searchDocuments($searchParams2, FALSE);
    $this->assertEquals(2, count($results2));
    // Cache hit does increase event numbers.
    $this->assertEquals(1, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    // Test fetching single document from cache with transaction id.
    $searchParamsSingle = [
      'transaction_id' => '1234567890123456',
    ];
    $results3 = $service->searchDocuments($searchParamsSingle, FALSE);
    $this->assertEquals(1, count($results3));
    $atvDocument = reset($results3);
    $this->assertEquals('id-1', $atvDocument->getId());
    // Cache hit does increase event numbers.
    $this->assertEquals(1, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    // And another one.
    $searchParamsSingle = [
      'transaction_id' => '1234567890123457',
    ];
    $results4 = $service->searchDocuments($searchParamsSingle, FALSE);
    $this->assertEquals(1, count($results4));
    $atvDocument = reset($results4);
    $this->assertEquals('id-2', $atvDocument->getId());
    // Cache hit does increase event numbers.
    $this->assertEquals(1, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());

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

  /**
   * Test and demonstrate loading logic for oma asiointi page.
   *
   * This is not really a test fro ATV module but it is a useful
   * test to improve things in grants project.
   */
  public function testGrantsSubmissionStorageLoadingLogic() {
    // 0. Do preparations for the test case.
    $mockClientFactory = \Drupal::service('http_client_factory');
    // Get the service for testing.
    $service = \Drupal::service('helfi_atv.atv_service');
    // Get event subscriber and reset it.
    $eventSubscriber = \Drupal::service('helfi_atv_test.event_subscriber');
    $eventSubscriber->resetCounters();

    // 1. Get list of all applications for a community.
    // \Drupal\grants_oma_asionti\Controller\OmaAsiointiController::build
    // \Drupal\grants_handler\ApplicationHandler::getCompanyApplications
    $mockResult1 = [
      'count' => 2,
      'results' => [
        [
          // Old format.
          'transaction_id' => 'GRANTS-TEST-LIIKUNTATAPAHTUMA-12345678',
          'id' => 'id-1',
        ],
        [
          // New format.
          'transaction_id' => 'TEST-059-87654321',
          'id' => 'id-2',
        ],
      ],
    ];
    $mockClientFactory->addResponse(new Response(200, [], json_encode($mockResult1)));
    // Search parameters for all application for the given community.
    $searchParams = [
      'service_name' => 'AvustushakemusIntegraatio',
      'business_id' => '1234567-1',
      'lookfor' => 'appenv:test,applicant_type:registered_community,',
    ];
    // Do the actual search and cache the results.
    $results = $service->searchDocuments($searchParams, FALSE);
    // Check that we got both results.
    $this->assertEquals(2, count($results));
    // One operation.
    $this->assertEquals(1, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());

    /* 2. After loading all the applications from ATV they are looped over in
    \Drupal\grants_handler\ApplicationHandler::getCompanyApplications. They are
    turned into submission objects and processing continues in
    \Drupal\grants_handler\GrantsHandlerSubmissionStorage::loadData. */
    $mockResult2 = [
      'count' => 0,
      'results' => [],
    ];
    $mockClientFactory->addResponse(new Response(200, [], json_encode($mockResult2)));
    // Mimic some logic from createApplicationNumber in ApplicationHander.
    $applicationNumber = self::getApplicationNumberInEnvFormat('TEST', '059', '12345678');
    // We get cache miss and empty result set.
    $results = $service->searchDocuments(
      [
        'transaction_id' => $applicationNumber,
        'lookfor' => 'appenv:TEST',
      ]
    );
    // Another operation.
    $this->assertEquals(2, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    $document = reset($results);
    $this->assertEquals(NULL, $document);
    // Try again with old id format.
    $applicationNumber = $this->getApplicationNumberInEnvFormatOldFormat('TEST', 'LIIKUNTATAPAHTUMA', '12345678');
    // This time we get a cache hit.
    $results = $service->searchDocuments(
      [
        'transaction_id' => $applicationNumber,
        'lookfor' => 'appenv:TEST',
      ]
    );
    // Cache hit does not increase event numbers.
    $this->assertEquals(2, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    /** @var \Drupal\helfi_atv\AtvDocument $document */
    $document = reset($results);
    // Test document id to check that we got corerct document.
    $this->assertEquals('id-1', $document->getId());

    // Do the same for second application.
    // Mimic some logic from createApplicationNumber in ApplicationHander.
    $applicationNumber = self::getApplicationNumberInEnvFormat('TEST', '059', '87654321');
    // We get cache miss and empty result set.
    $results = $service->searchDocuments(
      [
        'transaction_id' => $applicationNumber,
        'lookfor' => 'appenv:TEST',
      ]
    );
    // Cache hit does not increase event numbers.
    $this->assertEquals(2, $eventSubscriber->getOperationCount());
    $this->assertEquals(0, $eventSubscriber->getExceptionCount());
    /** @var \Drupal\helfi_atv\AtvDocument $document */
    $document = reset($results);
    // Test document id to check that we got corerct document.
    $this->assertEquals('id-2', $document->getId());
  }

}
