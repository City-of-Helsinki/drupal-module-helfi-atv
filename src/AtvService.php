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
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
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
   * How many seconds is data cached.
   *
   * @var int
   */
  protected int $queryCacheTime;

  /**
   * Atv version to use.
   *
   * @var string
   */
  protected string $atvVersion;

  /**
   * ATV service name string
   *
   * @var string
   */
  protected string $atvServiceName;

  /**
   * Helsinki profiili data.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiiliUserData;

  /**
   * Debug status.
   *
   * @var bool
   */
  protected bool $debug;

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
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *    Helsinkiprofiili
   */
  public function __construct(
    ClientInterface          $http_client,
    LoggerChannelFactory     $loggerFactory,
    FileRepository           $fileRepository,
    PrivateTempStoreFactory  $tempstore,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData
  ) {
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('helfi_atv');

    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;

    if (getenv('ATV_USE_TOKEN_AUTH') == 'true') {
      try {
        $tokens = $this->helsinkiProfiiliUserData->getApiAccessTokens();
        if (is_array($tokens)) {
          $this->headers = [
            'Authorization' => 'Bearer ' . $tokens['https://api.hel.fi/auth/atvapidev'],
          ];
        }
      } catch (\Exception $e) {
        $this->logger->error('%error', ['%error' => $e->getMessage()]);
      } catch (GuzzleException $e) {
        $this->logger->error('%error', ['%error' => $e->getMessage()]);
      }
    }
    else {
      $this->headers = [
        'X-Api-Key' => getenv('ATV_API_KEY'),
      ];
    }

    // @todo figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');
    $this->atvVersion = getenv('ATV_VERSION');

    $this->fileRepository = $fileRepository;
    $this->tempStore = $tempstore->get('atv_service');

    $this->useCache = getenv('ATV_USE_CACHE');

    $this->appEnvironment = getenv('APP_ENV');
    $this->atvServiceName = getenv('ATV_SERVICE');

    $qct = getenv('APP_QUERY_CACHE_TIME');

    if ($qct) {
      $this->queryCacheTime = intval($qct);
    }
    else {
      $this->queryCacheTime = 0;
    }

    $this->debug = getenv('debug');
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

    $requestStartTime = 0;
    if ($this->isDebug()) {
      $requestStartTime = floor(microtime(true) * 1000);;
    }

    $cacheKey = implode('-', $searchParams);

    if ($this->useCache && $refetch !== TRUE) {
      if ($this->isCached($cacheKey)) {
        return $this->getFromCache($cacheKey);
      }
    }

    $responseData = $this->doRequest(
      'GET',
      $this->buildUrl('documents', $searchParams),
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

    if ($this->isDebug()) {
      $requestEndTime = floor(microtime(true) * 1000);;
      $this->logger->debug('Search documents with @key took @ms ms', [
        '@key' => $cacheKey,
        '@ms' => $requestEndTime - $requestStartTime,
      ]);
    }

    return $responseData['results'];
  }

  /**
   * Get metadata for user's documents.
   *
   * If transaction id is given, then use that as a filter. If no value is
   * given, then get metadata of all user's documents.
   *
   * @param string $sub
   *   User id whose documents are fetched.
   * @param string $transaction_id
   *   Transaction id from document.
   *
   * @return array
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getUserDocuments(string $sub, string $transaction_id = ''): array {
    $params = [];
    if (!empty($transaction_id)) {
      $params['transaction_id'] = $transaction_id;
    }

    $responseData = $this->doRequest(
      'GET',
      $this->buildUrl('userdocuments/' . $sub, $params),
      [
        'headers' => [
          'X-Api-Key' => getenv('ATV_API_KEY'),
        ],
      ]
    );

    return $responseData['results'] ?? [];
  }

  /**
   * Build request url with params.
   *
   * @param string $endpoint
   *   Endpoint only.
   * @param array $params
   *   Params for url.
   *
   * @return string
   *   Built url
   */
  private function buildUrl(string $endpoint, array $params = []): string {

    // it seems that something adds ending slash on platta
    // this will make sure theres only one slash.
    if (str_ends_with($this->baseUrl, '/')) {
      $newUrl = $this->baseUrl . $this->atvVersion . '/' . $endpoint;
    }
    else {
      $newUrl = $this->baseUrl . '/' . $this->atvVersion . '/' . $endpoint;
    }

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
      $this->buildUrl('documents'),
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

    $formData = $this->arrayToFormData($document->toArray());

    $opts = [
      'headers' => $this->headers,
      // Form data.
      'multipart' => $formData,
    ];

    $response = $this->doRequest(
      'POST',
      $this->buildUrl('documents'),
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
    $patchUrl = 'documents/' . $id;

    // ATV does not allow user_id in PATCHed documents.
    if (isset($dataArray['user_id'])) {
      unset($dataArray['user_id']);
    }
    $formData = $this->arrayToFormData($dataArray);

    $opts = [
      'headers' => $this->headers,
      // Form data.
      'multipart' => $formData,
    ];

    $results = $this->doRequest(
      'PATCH',
      $this->buildUrl($patchUrl),
      $opts
    );

    $updatedDocument = reset($results['results']);

    if ($this->useCache && $updatedDocument) {
      $this->setToCache($dataArray["transaction_id"], [$updatedDocument]);
    }

    return $updatedDocument;
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
   * Delete document from ATV.
   *
   * @param \Drupal\helfi_atv\AtvDocument $document
   *   Document to be deleted.
   *
   * @return array|bool|\Drupal\file\FileInterface|\Drupal\helfi_atv\AtvDocument
   *   If deletion succeeed.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteDocument(AtvDocument $document) {
    $urlBits = 'documents/' . $document->getId();

    return $this->doRequest(
      'DELETE',
      $this->buildUrl($urlBits),
      [
        'headers' => $this->headers,
      ]
    );

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

    $url = 'documents/' . $documentId . '/attachments/' . $attachmentId;

    return $this->doRequest(
      'DELETE',
      $this->buildUrl($url),
      [
        'headers' => $this->headers,
      ]
    );
  }

  /**
   * Delete document attachment from ATV via intagration Id.
   *
   * @param string $integrationId
   *   Full URI of the attachment.
   *
   * @return array|bool|\Drupal\file\FileInterface|\Drupal\helfi_atv\AtvDocument
   *   If removal succeeed.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteAttachmentViaIntegrationId(string $integrationId): AtvDocument|bool|array|FileInterface {

    return $this->doRequest(
      'DELETE',
      $this->baseUrl . $integrationId,
      [
        'headers' => $this->headers,
      ]
    );
  }

  /**
   * Upload single attachment.
   *
   * File has to be saved to managed files for easier processing. Make sure file
   * is deleted after since this method does not delete.
   *
   * @param string $documentId
   *   Id of the document for this attachment.
   * @param string $filename
   *   Filename of the attachment.
   * @param \Drupal\file\Entity\File $file
   *   File to be uploaded.
   *
   * @return mixed
   *   Did upload succeed?
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function uploadAttachment(string $documentId, string $filename, File $file): mixed {

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

    $retval = $this->doRequest(
      'POST',
      $this->buildUrl('documents/' . $documentId . '/attachments'),
      [
        'headers' => $headers,
        'multipart' => [$data],
      ]
    );

    if (empty($retval)) {
      return FALSE;
    }

    return $retval;
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

    $requestStartTime = 0;
    if ($this->isDebug()) {
      $requestStartTime = floor(microtime(true) * 1000);;
    }
    $resp = $this->httpClient->request(
      $method,
      $url,
      $options
    );

    if ($this->isDebug()) {
      $requestEndTime = floor(microtime(true) * 1000);;
      $this->logger->debug('ATV @method query @url took @ms ms', [
        '@method' => $method,
        '@url' => $url,
        '@ms' => $requestEndTime - $requestStartTime,
      ]);
    }

    // Handle file download situation.
    $contentDisposition = $resp->getHeader('content-disposition');
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
          $resp->getBody()->getContents(),
          'private://grants_profile/' . $filename,
          FileSystemInterface::EXISTS_REPLACE
        );
      } catch (EntityStorageException $e) {
        // If fails, log error & return false.
        $this->logger->error('File download/filesystem write failed: ' . $e->getMessage());
      }
      $bodyContents = [
        'file' => $file,
      ];
    }
    else {
      $bc = $resp->getBody()->getContents();
      if (is_string($bc)) {
        $bodyContents = Json::decode($bc);
      }
      else {
        $bodyContents = [
          'results' => [],
        ];
      }
    }

    /** @var \GuzzleHttp\Psr7\Response */
    $bodyContents['response'] = $resp;

    if (isset($bodyContents['count'])) {
      if ($bodyContents['count'] !== count($bodyContents['results'])) {
        $bodyContents['results'] = array_merge($bodyContents['results'] ?? [], $prevRes);
        // Merge new results with old ones.
        if (isset($bodyContents['next']) && !empty($bodyContents['next'])) {
          // Call self for next results.
          $bodyContents = $this->request($method, $bodyContents['next'], $options, $bodyContents['results']);
        }
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
    array  $options
  ): array|AtvDocument|bool|FileInterface {
    try {


      $responseContent = $this->request(
        $method,
        $url,
        $options
      );

      /** @var \GuzzleHttp\Psr7\Response */
      $response = $responseContent['response'];

      if ($response->getStatusCode() == 204) {
        if ($method == 'DELETE') {
          return TRUE;
        }
      }

      // @todo Check if there's any point doing these if's here?!?!
      if ($response->getStatusCode() == 200) {

        // If we have response content as attachment, let's just return that.
        if (isset($responseContent['file'])) {
          return $responseContent['file'];
        }
        // If we have normal content, process that.
        if (isset($responseContent['results']) && is_array($responseContent['results'])) {
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
        $body = $response->getBody();
        $bodyContents = $body->getContents();
        if (is_string($bodyContents) && $bodyContents !== "") {
          $bodyContents = Json::decode($bodyContents);
          if (isset($bodyContents['results']) && is_array($bodyContents['results'])) {
            $resultDocuments = [];
            foreach ($bodyContents['results'] as $key => $value) {
              $resultDocuments[] = $this->createDocument($value);
            }
            $bodyContents['results'] = $resultDocuments;
          }
        }
        else {
          return $responseContent;
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

  /**
   * Whether or not we have made this query?
   *
   * @param string $key
   *   Used key for caching.
   *
   * @return bool
   *   Is this cached?
   */
  public function clearCache(string $key): bool {

    try {
      return $this->tempStore->delete($key);
    } catch (\Exception $e) {
      return FALSE;
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

    $now = time();

    if (isset($tempStoreData[$key]) && !empty($tempStoreData[$key])) {
      $dataArray = $tempStoreData[$key];
      $timeDiff = $now - $dataArray['timestamp'];
      if ($timeDiff < $this->queryCacheTime) {
        return TRUE;
      }
    }

    return FALSE;
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

    if (isset($tempStoreData[$key]) && !empty($tempStoreData[$key])) {
      if ($this->isDebug()) {
        $this->logger->debug('Request cache found @key', ['@key' => $key]);
      }
      return $tempStoreData[$key]['data'];
    }

    return NULL;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param mixed $data
   *   Cached data.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setToCache(string $key, mixed $data) {
    $tempStoreData = $this->tempStore->get('atv_service');

    $cacheTime = time();

    $tempStoreData[$key] = [
      'data' => $data,
      'timestamp' => $cacheTime,
    ];

    if ($this->isDebug()) {
      $this->logger->debug('Request updated @key', ['@key' => $key]);
    }

    $this->tempStore->set('atv_service', $tempStoreData);
  }

  /**
   * @return bool
   */
  public function isDebug(): bool {
    return $this->debug;
  }

  /**
   * @param bool $debug
   */
  public function setDebug(bool $debug): void {
    $this->debug = $debug;
  }

}
