<?php

namespace Drupal\helfi_atv;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepository;
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
   * Access to session storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Do we use caching or not?
   *
   * @var bool
   */
  protected bool $useCache;

  /**
   * Denotes the environment.
   *
   * @var string
   */
  protected string $appEnvironment;

  /**
   * Constructs an AtvService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger factory.
   * @param \Drupal\file\FileRepository $fileRepository
   *   Access to filesystem.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore
   *   Tempstore to save responses.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactory $loggerFactory,
    FileRepository $fileRepository,
    PrivateTempStoreFactory $tempstore
  ) {
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('helfi_atv');

    $this->headers = [
      'X-Api-Key' => getenv('ATV_API_KEY'),
    ];

    // @todo figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');

    $this->fileRepository = $fileRepository;
    $this->tempStore = $tempstore->get('atv_service');

    $this->useCache = getenv('ATV_USE_CACHE');

    $this->appEnvironment = getenv('APP_ENV');

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
   * @param bool $refetch
   *   Force refetch from ATV.
   *
   * @return array
   *   Data
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function searchDocuments(array $searchParams, bool $refetch = FALSE): array {

    $url = $this->buildUrl($searchParams);
    $cacheKey = implode('-', $searchParams);

    if ($this->useCache && $refetch === FALSE) {
      if ($this->isCached($cacheKey)) {
        return $this->getFromCache($cacheKey);
      }
    }

    $responseData = $this->doRequest(
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

    if ($this->useCache) {
      $this->setToCache($cacheKey, $responseData['results']);
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
   * @param bool $refetch
   *   Force refetch.
   *
   * @return \Drupal\helfi_atv\AtvDocument
   *   Document from ATV.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function getDocument(string $id, bool $refetch = FALSE): AtvDocument {

    if ($this->useCache && $refetch === FALSE) {
      if ($this->isCached($id)) {
        return $this->getFromCache($id);
      }
    }

    $response = $this->doRequest(
      'GET',
      $this->baseUrl . $id,
      [
        'headers' => $this->headers,
      ]
    );

    if ($this->useCache) {
      $this->setToCache($id, $response['results']);
    }

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

    $response = $this->doRequest(
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
  public function patchDocument(string $id, array $dataArray): bool|AtvDocument|NULL {
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

    $results = $this->doRequest(
      'PATCH',
      $patchUrl,
      $opts
    );

    return reset($results['results']);
  }

  /**
   * Get single attachment.
   *
   * @param string $url
   *   Url for single attachment file.
   *
   * @return bool|\Drupal\file\FileInterface
   *   File or false if failed.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAttachment(string $url): bool|FileInterface {
    $file = $this->doRequest(
      'GET',
      $url,
      [
        'headers' => $this->headers,
      ]
    );

    return $file ?? FALSE;
  }

  /**
   * Delete document attachment from ATV.
   *
   * @param string $documentId
   *   ID of document.
   * @param string $attachmentId
   *   ID of attachment.
   *
   * @return array|bool|\Drupal\file\FileInterface|\Drupal\helfi_atv\AtvDocument
   *   If removal succeeed.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteAttachment(string $documentId, string $attachmentId): AtvDocument|bool|array|FileInterface {

    $url = $this->baseUrl . $documentId . '/attachments/' . $attachmentId . '/';

    return $this->doRequest(
      'DELETE',
      $url,
      [
        'headers' => $this->headers,
      ]
    );
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
      $retval = $this->doRequest(
        'POST',
        $attachmentUrl,
        [
          'headers' => $headers,
          'multipart' => [$data],
        ]
      );

      return $retval['id'] ?? FALSE;
    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
      $this->logger->error($e->getMessage());
      return FALSE;
    }
    return FALSE;
  }

  /**
   * Execute requests with Guzzle, handle result paging.
   *
   * @param string $method
   *   Method for request.
   * @param string $url
   *   Url used.
   * @param array $options
   *   Options for request.
   * @param array $prevRes
   *   Earlier results in paged content.
   *
   * @return array
   *   Response data.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function request(string $method, string $url, array $options, array $prevRes = []): array {

    $resp = $this->httpClient->request(
      $method,
      $url,
      $options
    );

    $bc = $resp->getBody()->getContents();
    if (is_string($bc)) {
      $bodyContents = Json::decode($bc);
    }
    else {
      $bodyContents = [
        'results' => [],
      ];
    }

    /** @var \GuzzleHttp\Psr7\Response */
    $bodyContents['response'] = $resp;

    // Merge new results with old ones.
    $bodyContents['results'] = array_merge($bodyContents['results'], $prevRes);
    if ($bodyContents['count'] !== count($bodyContents['results'])) {
      if (isset($bodyContents['next']) && !empty($bodyContents['next'])) {
        // Call self for next results.
        $bodyContents = $this->request($method, $bodyContents['next'], $options, $bodyContents['results']);
      }
    }
    return $bodyContents;
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
  private function doRequest(
    string $method,
    string $url,
    array $options
  ): array|AtvDocument|bool|FileInterface {
    try {
      $responseContent = $this->request(
        $method,
        $url,
        $options
      );

      /** @var \GuzzleHttp\Psr7\Response */
      $response = $responseContent['response'];

      // @todo Check if there's any point doing these if's here?!?!
      if ($response->getStatusCode() == 200) {

        // Handle file download situation.
        $contentDisposition = $response->getHeader('content-disposition');
        $contentDisposition = reset($contentDisposition);

        $contentDispositionExplode = explode(';', $contentDisposition);
        if ($contentDispositionExplode[0] == 'attachment') {
          // If response is attachment.
          $filenameExplode = explode('=', $contentDispositionExplode[1]);
          $filename = $filenameExplode[1];
          $filename = str_replace('"', '', $filename);
          try {
            // Save file to filesystem & return File object.
            $file = $this->fileRepository->writeData(
              $response->getBody()->getContents(),
              'private://grants_profile/' . $filename,
              FileSystemInterface::EXISTS_REPLACE
            );
          }
          catch (EntityStorageException $e) {
            // If fails, log error & return false.
            $this->logger->error('File download/filesystem write failed: ' . $e->getMessage());
            return FALSE;
          }
          return $file;
        }

        if (is_array($responseContent['results'])) {
          $resultDocuments = [];
          foreach ($responseContent['results'] as $key => $value) {
            if (is_array($value)) {
              $resultDocuments[] = $this->createDocument($value);
            }
            else {
              $resultDocuments[] = $value;
            }

          }
          $responseContent['results'] = $resultDocuments;
        }
        else {
          if ($responseContent) {
            return [
              'results' => [$this->createDocument($responseContent)],
            ];
          }
          return FALSE;
        }
        return $responseContent;
      }
      if ($response->getStatusCode() == 201) {
        $bodyContents = $response->getBody()->getContents();
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

  /**
   * Whether or not we have made this query?
   *
   * @param string $key
   *   Used key for caching.
   *
   * @return bool
   *   Is this cached?
   */
  public function isCached(string $key): bool {
    $tempStoreData = $this->tempStore->get('atv_service');
    return isset($tempStoreData[$key]) && !empty($tempStoreData[$key]);
  }

  /**
   * Get item from cache.
   *
   * @param string $key
   *   Key to fetch from tempstore.
   *
   * @return mixed
   *   Data in cache or null
   */
  protected function getFromCache(string $key): mixed {
    $tempStoreData = $this->tempStore->get('atv_service');
    return (isset($tempStoreData[$key]) && !empty($tempStoreData[$key])) ? $tempStoreData[$key] : NULL;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param array $data
   *   Cached data.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setToCache(string $key, array $data) {
    $tempStoreData = $this->tempStore->get('atv_service');
    $tempStoreData[$key] = $data;
    $this->tempStore->set('atv_service', $tempStoreData);
  }

}
