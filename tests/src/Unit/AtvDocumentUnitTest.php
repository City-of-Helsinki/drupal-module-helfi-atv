<?php

namespace Drupal\Tests\helfi_atv\Unit;

use Drupal\helfi_atv\AtvDocument;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AtvDocument class.
 *
 * @covers \Drupal\helfi_atv\AtvDocument
 * @group helfi_atv
 */
class AtvDocumentUnitTest extends UnitTestCase {

  /**
   * Test hasAllowedRole method.
   */
  public function testCreate() {
    $dataArray = [
      'id' => 'test-id',
      'type' => 'type',
      'status' => [
        'value' => 'DRAFT',
      ],
      'status_histories' => [
        'DRAFT',
      ],
      'transaction_id' => '1234567890',
      'business_id' => '1234567-1',
      'tos_function_id' => '12345',
      'tos_record_id' => '54321',
      'draft' => TRUE,
      'human_readable_type' => ['humanType'],
      'metadata' => '{"name": "Name", "value": "Value"}',
      'content' => '{"data": "content"}',
      'created_at' => '2024-06-06',
      'updated_at' => '2024-06-07',
      'user_id' => 'userId',
      'locked_after' => '2024-06-08',
      // 'attachments' => [],
      'deletable' => TRUE,
      'delete_after' => '2075-01-01',
      'document_language' => 'fi',
      'content_schema_url' => 'schemaURL',
    ];
    $document = ATVDocument::create($dataArray);
    $statusHistory = $document->getStatusHistory();
    $this->assertEquals('DRAFT', $statusHistory[0]);
    $id = $document->getId();
    $this->assertEquals('test-id', $id);
    $isNew = $document->isNew();
    $this->assertEquals(FALSE, $isNew);

    // Time stamps.
    $createdAt = $document->getCreatedAt();
    $this->assertEquals('2024-06-06', $createdAt);
    $updatedAt = $document->getUpdatedAt();
    $this->assertEquals('2024-06-07', $updatedAt);

    $deleteAfter = $document->getDeleteAfter();
    $this->assertEquals('2075-01-01', $deleteAfter);

    $lockedAfter = $document->getLockedAfter();
    $this->assertEquals('2024-06-08', $lockedAfter);

    // Status and type.
    $status = $document->getStatus();
    $this->assertEquals('DRAFT', $status);
    $statusArray = $document->getStatusArray();
    $this->assertArrayHasKey('value', $statusArray);
    $this->assertEquals('DRAFT', $statusArray['value']);
    $type = $document->getType();
    $this->assertEquals('type', $type);

    // Ids.
    $tid = $document->getTransactionId();
    $this->assertEquals('1234567890', $tid);
    $uid = $document->getUserId();
    $this->assertEquals('userId', $uid);
    $businessId = $document->getBusinessId();
    $this->assertEquals('1234567-1', $businessId);
    $tosFunctionId = $document->getTosFunctionId();
    $this->assertEquals('12345', $tosFunctionId);
    $tosRecordId = $document->getTosRecordId();
    $this->assertEquals('54321', $tosRecordId);

    // Metadata.
    $metadata = $document->getMetadata();
    $this->assertIsArray($metadata);
    $this->assertCount(2, $metadata);
    $this->assertArrayHasKey('name', $metadata);
    $this->assertArrayHasKey('value', $metadata);
    $this->assertEquals('Name', $metadata['name']);
    $this->assertEquals('Value', $metadata['value']);

    $isDraft = $document->getDraft();
    $this->assertEquals(TRUE, $isDraft);

    $humanType = $document->getHumanReadableType();
    $this->assertEquals('humanType', $humanType[0]);
    $isDeletable = $document->isDeletable();
    $this->assertEquals(TRUE, $isDeletable);

    $language = $document->getDocumentLanguage();
    $this->assertEquals('fi', $language);

    $schemaUrl = $document->getContentSchemaUrl();
    $this->assertEquals('schemaURL', $schemaUrl);

    // Data and all getters are tested.
    $serializedDocument = $document->toArray();
    // Modify data array to match ATVDocument serialization.
    $dataArray['status_array'] = $dataArray['status'];
    $dataArray['status'] = 'DRAFT';
    $dataArray['metadata'] = ['name' => 'Name', 'value' => 'Value'];
    $dataArray['content'] = [
      'data' => 'content',
    ];
    ksort($dataArray);
    ksort($serializedDocument);
    $this->assertEqualsCanonicalizing(array_keys($dataArray), array_keys($serializedDocument));
    $this->assertEqualsCanonicalizing($dataArray, $serializedDocument);

    // Test addMetadata and setMetadata.
    $document->addMetadata('id', 'id');
    $metadata = $document->getMetadata();
    $this->assertIsArray($metadata);
    $this->assertCount(3, $metadata);
    $this->assertArrayHasKey('name', $metadata);
    $this->assertArrayHasKey('value', $metadata);
    $this->assertArrayHasKey('id', $metadata);
    $this->assertEquals('Name', $metadata['name']);
    $this->assertEquals('Value', $metadata['value']);
    $this->assertEquals('id', $metadata['id']);
    $document->setMetadata(['override' => 'value']);
    $metadata = $document->getMetadata();
    $this->assertIsArray($metadata);
    $this->assertCount(1, $metadata);
    $this->assertArrayNotHasKey('name', $metadata);
    $this->assertArrayNotHasKey('value', $metadata);
    $this->assertArrayNotHasKey('id', $metadata);
    $this->assertArrayHasKey('override', $metadata);
    $this->assertEquals('value', $metadata['override']);

    // Test other setters.
    // Status and type.
    $document->setStatus('SENT');
    $status = $document->getStatus();
    $this->assertEquals('SENT', $status);
    $document->setType('new-type');
    $type = $document->getType();
    $this->assertEquals('new-type', $type);

    // Ids.
    $document->setTransactionId('12345678901');
    $tid = $document->getTransactionId();
    $this->assertEquals('12345678901', $tid);
    $document->setUserId('newUserId');
    $uid = $document->getUserId();
    $this->assertEquals('newUserId', $uid);
    $document->setBusinessId('2345678-2');
    $businessId = $document->getBusinessId();
    $this->assertEquals('2345678-2', $businessId);
    $document->setTosFunctionId('23456');
    $tosFunctionId = $document->getTosFunctionId();
    $this->assertEquals('23456', $tosFunctionId);
    $document->setTosRecordId('65432');
    $tosRecordId = $document->getTosRecordId();
    $this->assertEquals('65432', $tosRecordId);

    $document->setDraft(FALSE);
    $isDraft = $document->getDraft();
    $this->assertEquals(FALSE, $isDraft);

    $document->setHumanReadableType(['cyborgType']);
    $humanType = $document->getHumanReadableType();
    $this->assertEquals('cyborgType', $humanType[0]);
    $document->setDeletable(FALSE);
    $isDeletable = $document->isDeletable();
    $this->assertEquals(FALSE, $isDeletable);

    $document->setDocumentLanguage('en');
    $language = $document->getDocumentLanguage();
    $this->assertEquals('en', $language);

    $document->setContentSchemaUrl('newSchemaURL');
    $schemaUrl = $document->getContentSchemaUrl();
    $this->assertEquals('newSchemaURL', $schemaUrl);

    $document->setDeleteAfter('2085-01-01');
    $deleteAfter = $document->getDeleteAfter();
    $this->assertEquals('2085-01-01', $deleteAfter);
  }

}
