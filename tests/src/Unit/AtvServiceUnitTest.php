<?php

namespace Drupal\Tests\helfi_atv\Unit;

use Drupal\helfi_atv\AtvService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AtvService class.
 *
 * @covers DefaultClass \Drupal\helfi_atva\AtvService
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
}
