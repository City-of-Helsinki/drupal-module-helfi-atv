<?php

namespace Drupal\helfi_atv_test;

use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;

class MockHelsinkiProfiiliUserData extends HelsinkiProfiiliUserData {
    public function getCurrentUserRoles(): array {
      return ['user', 'helsinkiprofiili'];
    }
}