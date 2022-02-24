<?php

namespace Drupal\helfi_atv;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepository;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Utils;

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
   * Access to file system.
   *
   * @var \Drupal\file\FileRepository
   */
  protected FileRepository $fileRepository;

  /**
   * Constructs an AtvService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger factory.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactory $loggerFactory,
    FileRepository $fileRepository
  ) {
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('helfi_atv');

    $this->headers = [
      'X-Api-Key' => getenv('ATV_API_KEY'),
    ];

    // @todo figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');

    $this->fileRepository = $fileRepository;

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
    if (!is_array($responseData) || empty($responseData['results'])) {
      throw new AtvDocumentNotFoundException('No documents found in ATV');
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
    if (!str_ends_with($newUrl, '/') && !str_contains($newUrl, '?')) {
      return $newUrl . '/';
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

    $response = $this->request(
      'GET',
      $this->baseUrl . $id,
      [
        'headers' => $this->headers,
      ]
    );

    return reset($response['results']);

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
   * @param array $dataArray
   *   Document data to update.
   *
   * @return bool|\Drupal\helfi_atv\AtvDocument|null
   *   Boolean or updated data.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function patchDocument(string $id, array $dataArray): bool|AtvDocument|null {
    $patchUrl = $this->baseUrl . $id;

    if (!str_ends_with($patchUrl, '/') && !str_contains($patchUrl, '?')) {
      $patchUrl = $patchUrl . '/';
    }

    $formData = $this->arrayToFormData($dataArray);

    $opts = [
      'headers' => $this->headers,
      // Form data.
      'multipart' => $formData,
    ];

    $results = $this->request(
      'PATCH',
      $patchUrl,
      $opts
    );

    return reset($results['results']);
  }

  /**
   * Get document attachments.
   */
  public function getAttachments() {

  }

  /**
   * Get single attachment.
   *
   * @param $url
   *  Url for single attachment file.
   *
   * @return bool|\Drupal\file\FileInterface
   *  File or false if failed.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAttachment($url): bool|FileInterface {
    $file = $this->request(
      'GET',
      $url,
      [
        'headers' => $this->headers,
      ]
    );

    return $file ?? FALSE;

  }

  /**
   * Get single attachment.
   *
   * @param string $documentId
   *   Id of the document for this attachment.
   * @param string $filename
   *   Filename of the attachment.
   * @param \Drupal\file\Entity\File $file
   *   File to be uploaded.
   *
   * @return false|mixed
   *   Did upload succeed?
   */
  public function uploadAttachment(string $documentId, string $filename, File $file) {

    $attachmentUrl = $this->baseUrl . $documentId . '/attachments/';

    $headers = $this->headers;
    $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
    $headers['Content-Type'] = 'application/octet-stream';

    // Get file metadata.
    $fileUri = $file->get('uri')->value;
    $filePath = \Drupal::service('file_system')->realpath($fileUri);

    // Get file data.
    $body = Utils::tryFopen($filePath, 'r');

    // Form data.
    $data = [
      'name' => $filename,
      'filename' => $filename,
      'contents' => $body,
    ];

    try {
      $retval = $this->request(
        'POST',
        $attachmentUrl,
        [
          'headers' => $headers,
          'multipart' => [$data],
        ]
      );

      return $retval['id'] ?? FALSE;
    } catch (AtvDocumentNotFoundException|AtvFailedToConnectException|GuzzleException $e) {
      $this->logger->error($e->getMessage());
      return FALSE;
    }
    return FALSE;
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
   * @return array|bool|FileInterface|AtvDocument
   *   Content or boolean if void.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function request(string $method, string $url, array $options): array|AtvDocument|bool|FileInterface {

    try {
      $resp = $this->httpClient->request(
        $method,
        $url,
        $options
      );

      // @todo Check if there's any point doing these if's here?!?!
      if ($resp->getStatusCode() == 200) {

        // handle file download situation.
        $contentDisposition = $resp->getHeader('content-disposition');
        $contentDisposition = reset($contentDisposition);

        $contentDispositionExplode = explode(';', $contentDisposition);
        if ($contentDispositionExplode[0] == 'attachment') {
          // if response is attachment
          $filenameExplode = explode('=',$contentDispositionExplode[1]);
          $filename = $filenameExplode[1];
          $filename = str_replace('"', '', $filename);
          try {
            // save file to filesystem & return File object
            $file = $this->fileRepository->writeData(
              $resp->getBody()->getContents(),
              'private://grants_profile/' . $filename,
              FileSystemInterface::EXISTS_REPLACE
            );
          } catch (EntityStorageException $e) {
            // if fails, log error & return false
            $this->logger->error('File download/filesystem write failed: '.$e->getMessage());
            return FALSE;
          }
          return $file;
        }


        $bodyContents = $resp->getBody()->getContents();
        if (is_string($bodyContents)) {
          $bodyContents = Json::decode($bodyContents);
        }
        if (isset($bodyContents['results']) && is_array($bodyContents['results'])) {
          $resultDocuments = [];
          foreach ($bodyContents['results'] as $key => $value) {
            $resultDocuments[] = $this->createDocument($value);
          }
          $bodyContents['results'] = $resultDocuments;
        }
        else {
          return [
            'results' => [$this->createDocument($bodyContents)],
          ];
        }
        return $bodyContents;
      }
      if ($resp->getStatusCode() == 201) {
        $bodyContents = $resp->getBody()->getContents();
        if (is_string($bodyContents)) {
          $bodyContents = Json::decode($bodyContents);
        }
        if (isset($bodyContents['results']) && is_array($bodyContents['results'])) {
          $resultDocuments = [];
          foreach ($bodyContents['results'] as $key => $value) {
            $resultDocuments[] = $this->createDocument($value);
          }
          $bodyContents['results'] = $resultDocuments;
        }
        return $bodyContents;
      }
      return FALSE;
    } catch (ServerException|GuzzleException $e) {
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
