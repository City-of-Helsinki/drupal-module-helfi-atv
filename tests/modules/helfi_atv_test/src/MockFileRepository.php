<?php

namespace Drupal\helfi_atv_test;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepository;

/**
 * Mock for file repository.
 */
class MockFileRepository extends FileRepository {

  /**
   * Mock version.
   */
  public function writeData(string $data, string $destination, int $replace = FileSystemInterface::EXISTS_RENAME): FileInterface {
    $fileName = __DIR__ . '/uploadAttachment.txt';
    $file = File::create(['uri' => $fileName]);
    return $file;
  }

}
