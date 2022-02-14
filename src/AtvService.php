<?php

namespace Drupal\helfi_atv;

use Drupal\Core\Logger\LoggerChannelFactory;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;

/**
 * Communicate with ATV.
 */
class AtvService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Headers for requests.
   *
   * @var array
   */
  protected array $headers;

  /**
   * Base endpoint.
   *
   * @var string
   */
  private string $baseUrl;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Constructs an AtvService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger factory.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelFactory $loggerFactory) {
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('helfi_atv');

    $this->headers = [
      'X-Api-Key' => getenv('ATV_API_KEY'),
    ];

    // @todo figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');

  }

  /**
   * Create new ATVDocument.
   *
   * @param array $values
   *   Values for data.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   Data in object struct.
   */
  public function createDocument(array $values): AtvDocument {
    return AtvDocument::create($values);
  }

  /**
   * Search documents with given arguments.
   *
   * @param array $searchParams
   *   Search params.
   *
   * @return array
   *   Data
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function searchDocuments(array $searchParams): array {

    $url = $this->buildUrl($searchParams);

    $responseData = $this->request(
      'GET',
      $url,
      [
        'headers' => $this->headers,
      ]
    );

    // If no data for some reason, don't fail, return empty array instead.
    if (!is_array($responseData)) {
      return [];
    }

    return $responseData['results'];

  }

  /**
   * Build request url with params.
   *
   * @param array $params
   *   Params for url.
   *
   * @return string
   *   Built url
   */
  private function buildUrl(array $params): string {
    $newUrl = $this->baseUrl;

    if (!empty($params)) {
      $paramCounter = 1;
      foreach ($params as $key => $value) {
        if ($paramCounter == 1) {
          $newUrl .= '?';
        }
        else {
          $newUrl .= '&';
        }
        $newUrl .= $key . '=' . $value;
        $paramCounter++;
      }
    }
    return $newUrl;
  }

  /**
   * Fetch single document with id.
   *
   * @param string $id
   *   Document id.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   Document from ATV.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getDocument(string $id): AtvDocument {

    $responseData = $this->request(
     'GET',
     $this->baseUrl . $id,
     [
       'headers' => $this->headers,
     ]
     );

    $responseData['content'] = $this->parseContent($responseData['content']);

    return AtvDocument::create($responseData);

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
  public function parseContent(string $contentString): mixed {
    $replaced = str_replace("'", "\"", $contentString);
    $replaced = str_replace("False", "false", $replaced);

    return Json::decode($replaced);
  }

  /**
   * Parse array data to form data.
   *
   * @param array $document
   *   Document data.
   *
   * @return array
   *   Array data in formdata structure.
   */
  protected function arrayToFormData(array $document): array {
    $retval = [];
    foreach ($document as $key => $value) {
      if (is_array($value)) {
        $contents = Json::encode($value);
      }
      else {
        $contents = $value;
      }
      $retval[$key] = [
        'name' => $key,
        'contents' => $contents,
      ];
    }
    return $retval;
  }

  /**
   * Save new document.
   *
   * @param \Drupal\helfi_atv\AtvDocument $document
   *   Document to be saved.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   POSTed document.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function postDocument(AtvDocument $document): AtvDocument {
    $postUrl = $this->baseUrl;

    $formData = $this->arrayToFormData($document->toArray());

    $opts = [
      'headers' => $this->headers,
      // Form data.
      'multipart' => $formData,
    ];

    $response = $this->request(
      'POST',
      $postUrl,
      $opts
    );

    return AtvDocument::create($response);
  }

  /**
   * Run PATCH query in ATV.
   *
   * @param string $id
   *   Document id to be patched.
   * @param array $document
   *   Document data to update.
   *
   * @return bool|null
   *   If PATCH succeeded?
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function patchDocument(string $id, array $document): ?bool {
    $patchUrl = $this->baseUrl . $id;

    $content = JSON::encode((object) $document);

    $headers = array_merge($this->headers, ['Content-Type' => 'application/json']);

    return $this->request(
      'PATCH',
      $patchUrl,
      [
        'headers' => $headers,
        'body' => $content,
      ]
    );
  }

  /**
   * Get document attachments.
   */
  public function getAttachments() {

  }

  /**
   * Get single attachment.
   *
   * @param string $id
   *   Id of attachment.
   */
  public function getAttachment(string $id) {

  }

  /**
   * Request wrapper for error handling.
   *
   * @param string $method
   *   Method for request.
   * @param string $url
   *   Endpoint.
   * @param array $options
   *   Options for request.
   *
   * @return bool|array
   *   Content or boolean if void.
   *
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   */
  private function request(string $method, string $url, array $options): bool|array {

    try {
      $resp = $this->httpClient->request(
        $method,
        $url,
        $options
      );
      // @todo Check if there's any point doing these if's here?!?!
      if ($resp->getStatusCode() == 200) {
        if ($method == 'GET') {
          $bodyContents = $resp->getBody()->getContents();
          if (is_string($bodyContents)) {
            return Json::decode($bodyContents);
          }
          return $bodyContents;
        }
        else {
          return TRUE;
        }
      }
      if ($resp->getStatusCode() == 201) {
        $bodyContents = $resp->getBody()->getContents();
        if (is_string($bodyContents)) {
          $bc = Json::decode($bodyContents);
          return $bc;
        }
        return $bodyContents;
      }
      return FALSE;
    }
    catch (ServerException | GuzzleException $e) {
      $msg = $e->getMessage();

      $this->logger->error($msg);

      if (str_contains($msg, 'cURL error 7')) {
        throw new AtvFailedToConnectException($msg);
      }
      elseif ($e->getCode() === 404) {
        throw new AtvDocumentNotFoundException('Document not found');
      }
      else {
        throw $e;
      }
    }
  }
}
