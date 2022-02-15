<?php

namespace Drupal\helfi_atv;

use Drupal\Component\Serialization\Json;
use JetBrains\PhpStorm\Pure;

/**
 * Document model in ATV.
 */
final class AtvDocument implements \JsonSerializable {

  /**
   * Document UUID.
   *
   * @var string
   */
  protected string $id;

  /**
   * Created time.
   *
   * @var string
   */
  protected string $createdAt;

  /**
   * Updated time.
   *
   * @var string
   */
  protected string $updatedAt;

  /**
   * Document status.
   *
   * @var string
   */
  protected string $status;

  /**
   * Document type.
   *
   * @var string
   */
  protected string $type;

  /**
   * Service name.
   *
   * @var string
   */
  protected string $service;

  /**
   * Transaction id.
   *
   * @var string
   */
  protected string $transactionId;

  /**
   * User id.
   *
   * @var string
   */
  protected string $userId;

  /**
   * Business id.
   *
   * @var string
   */
  protected string $businessId;

  /**
   * TOS function.
   *
   * @var string
   */
  protected string $tosFunctionId;

  /**
   * TOS record.
   *
   * @var string
   */
  protected string $tosRecordId;

  /**
   * Document metadata.
   *
   * @var array
   */
  protected array $metadata;

  /**
   * Is document draft.
   *
   * This will probably be deprecated at some point.
   *
   * @var bool
   */
  protected bool $draft;

  /**
   * Locked after, after this time editing of this document will be prohibited.
   *
   * @var string
   */
  protected string $lockedAfter;

  /**
   * Document content. Encrypted in ATV.
   *
   * @var array
   */
  protected array $content;

  /**
   * Document attachments.
   *
   * @var array
   */
  protected array $attachments;

  /**
   * Url to document.
   *
   * @var string
   */
  protected string $href;

  /**
   * Create ATVDocument object from given values.
   *
   * @param array $values
   *   Values for document.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   Document created from values.
   */
  public static function create(array $values): AtvDocument {
    $object = new self();
    if (isset($values['id'])) {
      $object->id = $values['id'];
    }
    if (isset($values['type'])) {
      $object->type = $values['type'];
    }
    if (isset($values['service'])) {
      $object->service = $values['service'];
    }
    if (isset($values['status'])) {
      $object->status = $values['status'];
    }
    if (isset($values['transaction_id'])) {
      $object->transactionId = $values['transaction_id'];
    }
    if (isset($values['business_id'])) {
      $object->businessId = $values['business_id'];
    }
    if (isset($values['tos_function_id'])) {
      $object->tosFunctionId = $values['tos_function_id'];
    }
    if (isset($values['tos_record_id'])) {
      $object->tosRecordId = $values['tos_record_id'];
    }
    if (isset($values['draft'])) {
      $object->draft = $values['draft'];
    }
    if (isset($values['metadata'])) {
      // Make sure metadata is decoded if it's an string
      if (is_string($values['metadata'])) {
        $object->metadata = Json::decode($values['metadata']);
      } else {
        $object->metadata = $values['metadata'];
      }
    }
    if (isset($values['content'])) {
      // Make sure content is decoded if it's an string
      if (is_string($values['content'])) {
        $object->content = self::parseContent($values['content']);
      } else {
        $object->content = $values['content'];
      }
    }
    if (isset($values['created_at'])) {
      $object->createdAt = $values['created_at'];
    }
    if (isset($values['updated_at'])) {
      $object->updatedAt = $values['updated_at'];
    }
    if (isset($values['user_id'])) {
      $object->userId = $values['user_id'];
    }
    if (isset($values['locked_after'])) {
      $object->lockedAfter = $values['locked_after'];
    }
    if (isset($values['attachments'])) {
      $object->attachments = $values['attachments'];
    }

    return $object;
  }

  /**
   * Parse malformed json.
   *
   * @param string $contentString
   *   JSON to be checked.
   *
   * @return mixed
   *   Decoded JSON array.
   */
  public static function parseContent(string $contentString): mixed {
    $replaced = str_replace("'", "\"", $contentString);
    $replaced = str_replace("False", "false", $replaced);

    return Json::decode($replaced);
  }

  /**
   * Helper function to json_encode to handle object values.
   *
   * @return array
   *   Array structure for this object.
   */
  public function jsonSerialize(): array {
    return $this->toArray();
  }

  /**
   * Encode this object to json.
   *
   * @return false|string
   *   This encoded in json.
   */
  public function toJson(): bool|string {
    return Json::encode($this);
  }

  /**
   * Helper function to be used with json.
   *
   * @return array
   *   This object exported to array struct.
   */
  public function toArray(): array {
    $json_array = [];

    if (isset($this->id)) {
      $json_array['id'] = $this->id;
    }
    // If (isset($this->service)) {
    // $json_array['service'] = $this->service;
    // }
    // if (isset($this->userId)) {
    // $json_array['user_id'] = $this->getUserId();
    // }
    if (isset($this->createdAt)) {
      $json_array['created_at'] = $this->createdAt;
    }
    if (isset($this->updatedAt)) {
      $json_array['updated_at'] = $this->getUpdatedAt();
    }
    if (isset($this->status)) {
      $json_array['status'] = $this->getStatus();
    }
    if (isset($this->type)) {
      $json_array['type'] = $this->getType();
    }
    if (isset($this->transactionId)) {
      $json_array['transaction_id'] = $this->getTransactionId();
    }

    if (isset($this->businessId)) {
      $json_array['business_id'] = $this->getBusinessId();
    }
    if (isset($this->tosFunctionId)) {
      $json_array['tos_function_id'] = $this->getTosFunctionId();
    }
    if (isset($this->tosFunctionId)) {
      $json_array['tos_function_id'] = $this->getTosFunctionId();
    }
    if (isset($this->tosRecordId)) {
      $json_array['tos_record_id'] = $this->getTosRecordId();
    }
    if (isset($this->metadata)) {
      $json_array['metadata'] = $this->getMetadata();
    }
    if (isset($this->content)) {
      $json_array['content'] = $this->getContent();
    }

    return $json_array;
  }

  /**
   * Get service value.
   *
   * @return string
   *   Document service.
   */
  public function getService(): string {
    return $this->service;
  }

  /**
   * Get id.
   *
   * @return string
   *   Document ID.
   */
  public function getId(): string {
    return $this->id ?? '';
  }

  /**
   * Is this document new?
   *
   * @return bool
   */
  #[Pure] public function isNew(): bool {
    return empty($this->getId());
  }

  /**
   * Get creation time.
   *
   * @return string
   *   Document created time.
   */
  public function getCreatedAt(): string {
    return $this->createdAt;
  }

  /**
   * Get update time.
   *
   * @return string
   *   Document update time
   */
  public function getUpdatedAt(): string {
    return $this->updatedAt;
  }

  /**
   * Get document status.
   *
   * @return string
   *   Document status
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Get document type.
   *
   * @return string
   *   Document type.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Get transaction id.
   *
   * @return string
   *   Document transaction ID
   */
  public function getTransactionId(): string {
    return $this->transactionId;
  }

  /**
   * Get user id.
   *
   * @return string
   *   Document user id.
   */
  public function getUserId(): string {
    return $this->userId;
  }

  /**
   * Get business id.
   *
   * @return string
   *   Document business ID.
   */
  public function getBusinessId(): string {
    return $this->businessId;
  }

  /**
   * Get TOS function.
   *
   * @return string
   *   Document TOS function.
   */
  public function getTosFunctionId(): string {
    return $this->tosFunctionId;
  }

  /**
   * Get TOS record.
   *
   * @return string
   *   Document TOS record.
   */
  public function getTosRecordId(): string {
    return $this->tosRecordId;
  }

  /**
   * Get metadata.
   *
   * @return array
   *   Document metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Set metadata.
   *
   * @param array $metadata
   *   New metadata.
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Set metadata.
   *
   * @param string $status
   */
  public function setStatus(string $status): void {
    $this->status = $status;
  }

  /**
   * Set metadata.
   *
   * @param string $key
   *   Metadata key.
   * @param array $value
   *   Metadata value for given key.
   */
  public function addMetadata(string $key, array $value): void {
    $this->metadata[$key] = $value;
  }

  /**
   * Get document draft status.
   *
   * @return bool
   *   Document draft status.
   */
  public function getDraft(): bool {
    return $this->draft;
  }

  /**
   * Get document locked after date.
   *
   * @return string
   *   Document locked after date.
   */
  public function getLockedAfter(): string {
    return $this->lockedAfter;
  }

  /**
   * Get document content.
   *
   * @return array
   *   Document content.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Set document content.
   *
   * @param array $content
   *   Document content.
   */
  public function setContent(array $content): void {
    $this->content = $content;
  }

  /**
   * Get document attachments.
   *
   * @return array
   *   Document attachments.
   */
  public function getAttachments(): array {
    return $this->attachments;
  }

  /**
   * Get document link.
   *
   * @return string
   *   Document url.
   */
  public function getHref(): string {
    return $this->href;
  }

  /**
   * @param string $transactionId
   */
  public function setTransactionId(string $transactionId): void {
    $this->transactionId = $transactionId;
  }
}
