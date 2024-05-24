<?php

namespace Drupal\Tests\helfi_atv\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\helfi_atv\AtvService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AtvService class.
 *
 * @covers \Drupal\helfi_atv\AtvService
 * @group helfi_atv
 */
class AtvServiceUnitTest extends UnitTestCase {

  /**
   * Test hasAllowedRole method.
   */
  public function testHasAllowedRole() {
    // Test 1.
    $allowedRoles1 = ['a1', 'a2', 'a3'];
    $userRoles1 = ['a3', 'b3', 'c3'];
    $result1 = AtvService::hasAllowedRole($allowedRoles1, $userRoles1);
    $this->assertEquals(TRUE, $result1);
    // Test2.
    $allowedRoles2 = ['a1', 'a2', 'a3'];
    $userRoles2 = ['b1', 'b2', 'b3'];
    $result2 = AtvService::hasAllowedRole($allowedRoles2, $userRoles2);
    $this->assertEquals(FALSE, $result2);
    // Test 3.
    $allowedRoles3 = ['d1'];
    $userRoles3 = ['d1'];
    $result3 = AtvService::hasAllowedRole($allowedRoles3, $userRoles3);
    $this->assertEquals(TRUE, $result3);
    // Test 4.
    $allowedRoles4 = [];
    $userRoles4 = [];
    $result4 = AtvService::hasAllowedRole($allowedRoles4, $userRoles4);
    $this->assertEquals(FALSE, $result4);

  }

  /**
   * Test arrayWalkRecursive.
   */
  public function testArrayWalkRecursive() {
    $arrayData1a = [
      'test1' => [],
      'test2' => [],
      'test3' => [],
    ];
    $json1a = Json::encode($arrayData1a);
    $this->assertEquals('{"test1":[],"test2":[],"test3":[]}', $json1a);
    $arrayData1b = [
      'test1' => [],
      'test2' => [],
      'test3' => [],
    ];
    $json1b = Json::encode(AtvService::arrayWalkRecursive($arrayData1b));
    $this->assertEquals('{"test1":{},"test2":{},"test3":{}}', $json1b);

    $arrayData2 = [
      'test1' => ['value' => '2'],
      'test2' => ['value' => 'string'],
      'test3' => ['value' => ''],
    ];
    $json2 = Json::encode(AtvService::arrayWalkRecursive($arrayData2));
    $this->assertEquals('{"test1":{"value":"2"},"test2":{"value":"string"},"test3":{"value":""}}', $json2);
    $arrayData3 = [
      [
        'test1' => [],
        'test2' => [],
        'test3' => [],
      ],
      [
        'test4' => [],
        'test5' => [],
        'test6' => [],
      ],
    ];
    $json3 = Json::encode(AtvService::arrayWalkRecursive($arrayData3));
    $this->assertEquals('[{"test1":{},"test2":{},"test3":{}},{"test4":{},"test5":{},"test6":{}}]', $json3);
    // Test Xss filtering.
    $arrayData4 = [
      'test1' => ['value' => '<b>Bold<b>'],
      'test2' => ['value' => '<script>evil</script>'],
      'test3' => ['value' => ''],
      'test4' => [],
    ];
    $json4 = Json::encode(AtvService::arrayWalkRecursive($arrayData4));
    $this->assertEquals('{"test1":{"value":"Bold"},"test2":{"value":"evil"},"test3":{"value":""},"test4":{}}', $json4);

  }

}
