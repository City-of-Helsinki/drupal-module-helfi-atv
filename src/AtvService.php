<?php

namespace Drupal\helfi_atv;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepository;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\helfi_helsinki_profiili\TokenExpiredException;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;
use Drupal\helfi_atv\Event\AtvServiceExceptionEvent;
use Drupal\Component\Utility\Xss;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
  protected string $baseUrl;

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
   * Atv version to use.
   *
   * @var string
   */
  protected string $atvVersion;

  /**
   * ATV service name string.
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
   * Token name to use with atv.
   *
   * @var string
   */
  protected string $atvTokenName;

  /**
   * Request cache.
   *
   * @var array
   */
  protected array $requestCache;

  /**
   * Maximum amount pages to fetch. This is not to overflow things.
   *
   * @var int
   */
  protected int $maxPages;

  /**
   * Current call count with multi-pages results.
   *
   * @var int
   */
  protected int $callCount;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs an AtvService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger factory.
   * @param \Drupal\file\FileRepository $fileRepository
   *   Access to filesystem.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *   Helsinkiprofiili.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactory $loggerFactory,
    FileRepository $fileRepository,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    EventDispatcherInterface $eventDispatcher,
  ) {
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('helfi_atv');
    $this->eventDispatcher = $eventDispatcher;

    $this->baseUrl = getenv('ATV_BASE_URL');
    $this->atvVersion = getenv('ATV_VERSION');
    $this->useCache = getenv('ATV_USE_CACHE');

    $this->appEnvironment = getenv('APP_ENV');
    $this->atvServiceName = getenv('ATV_SERVICE');

    $this->helsinkiProfiiliUserData = $helsinkiProfiiliUserData;

    $this->fileRepository = $fileRepository;

    $debug = getenv('DEBUG');

    if ($debug == 'true' || $debug === TRUE) {
      $this->debug = TRUE;
    }
    else {
      $this->debug = FALSE;
    }

    $this->requestCache = [];
    $this->headers = [];

    if (getenv('ATV_MAX_PAGES')) {
      $this->maxPages = getenv('ATV_MAX_PAGES');
    }
    else {
      $this->maxPages = 10;
    }

    $this->callCount = 0;
  }

  /**
   * Set authentication headers depending on user session.
   *
   * @param bool $useApiKey
   *   Force use of api key authentication.
   * @param string|null $token
   *   Use this token for auth. Leave null for user token.
   *
   * @throws AtvAuthFailedException
   * @throws \Drupal\helfi_helsinki_profiili\TokenExpiredException
   */
  private function setAuthHeaders(bool $useApiKey = FALSE, string $token = NULL): void {
    // If apikey usage is forced, use it.
    if ($useApiKey) {
      $this->debugPrint('setAuthHeaders useApiKey: @tokeauth', ['@tokeauth' => $useApiKey]);
      $this->headers = [
        'X-Api-Key' => getenv('ATV_API_KEY'),
      ];
      return;
    }
    // Else if token is given, use it instead of getting one from HP.
    elseif ($token) {
      $this->debugPrint('ATV Token given, bypass HP token fetching, got token: @token', ['@token' => $token]);

      $this->headers = [
        'Authorization' => 'Bearer ' . $token,
      ];
      return;
    }

    $useTokenAuth = getenv('ATV_USE_TOKEN_AUTH');

    $this->debugPrint('setAuthHeaders-> User tokenAUTH: @tokeauth', ['@tokeauth' => $useTokenAuth]);

    // Here we figure out if user has HP user or ADMIN, and if user has admin
    // role but no user role then we use apikey for authenticating user.
    $userRoles = $this->helsinkiProfiiliUserData->getCurrentUser()->getRoles();

    $config = \Drupal::config('helfi_atv.settings');
    $rolesConfig = $config->get('roles');

    $adminRoles = $rolesConfig['admin_user_roles'] ?? [];
    $hpRoles = $rolesConfig['hp_user_roles'] ?? [];

    if (in_array('admin', $userRoles)) {
      return;
    }

    // Has user admin role?
    $hasAdminRole = array_reduce($userRoles,
      function ($carry, $value) use ($adminRoles) {
        if (in_array($value, $adminRoles)) {
          return TRUE;
        }
      },
      FALSE,
    );
    // What about helsinki profile role then?
    $hasHpRole = array_reduce($userRoles,
      function ($carry, $value) use ($hpRoles) {
        if (in_array($value, $hpRoles)) {
          return TRUE;
        }
      },
      FALSE,
    );

    $this->debugPrint('setAuthHeaders-> User roles: @roles', ['@roles' => Json::encode($userRoles)]);

    // If user does not have admin role but has user role,
    // use token based auth.
    // Token based auth must be explicitly set to true to
    // enable token based auth.
    if ($hasAdminRole !== TRUE && $hasHpRole === TRUE && $useTokenAuth == 'true') {

      $this->debugPrint('setAuthHeaders-> Token auth & no admin role but HAS HP role');

      $tokenName = getenv('ATV_TOKEN_NAME');
      if (!empty($tokenName)) {
        $this->atvTokenName = $tokenName;
      }
      else {
        throw new AtvAuthFailedException('No auth token name set.');
      }

      $tokens = $this->helsinkiProfiiliUserData->getApiAccessTokens();
      if (is_array($tokens) && isset($tokens[$this->atvTokenName])) {

        $this->debugPrint('ATV Token auth, got tokens: @tokens', ['@tokens' => Json::encode(array_keys($tokens))]);

        $this->headers = [
          'Authorization' => 'Bearer ' . $tokens[$this->atvTokenName],
        ];
      }
      else {
        $this->debugPrint('ATV Token auth, tokens FAIL: @tokens', ['@tokens' => Json::encode($tokens)]);
      }

    }
    // If user has admin role, then use apikey.
    // Or if the token usage has been disabled.
    elseif ($hasAdminRole == TRUE || $useTokenAuth == 'false') {
      $this->debugPrint('Has admin role + tries API key');
      $this->headers = [
        'X-Api-Key' => getenv('ATV_API_KEY'),
      ];
    }
    else {
      $this->headers = [];
      $this->logger->error('User is trying to access ATV but has not been externally authenticated.');
    }
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
   * Recursively implodes an array with optional key inclusion.
   *
   * @param string $glue
   *   Value that glues elements together.
   * @param array $array
   *   Multi-dimensional array to recursively implode.
   * @param bool $include_keys
   *   Include keys before their values.
   * @param bool $trim_all
   *   Trim ALL whitespace from string.
   *
   * @return string
   *   Imploded array
   */
  private static function recursiveImplode(string $glue, array $array, $include_keys = FALSE, $trim_all = TRUE) {
    $glued_string = '';

    // Recursively iterates array and adds key/value to glued string.
    array_walk_recursive($array, function ($value, $key) use ($glue, $include_keys, &$glued_string) {
      $include_keys && $glued_string .= $key . $glue;
      $glued_string .= $value . $glue;
    });

    // Removes last $glue from string.
    strlen($glue) > 0 && $glued_string = substr($glued_string, 0, -strlen($glue));

    // Trim ALL whitespace.
    $trim_all && $glued_string = preg_replace("/(\s)/ixsm", '', $glued_string);

    return (string) $glued_string;
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
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function searchDocuments(array $searchParams, bool $refetch = FALSE): array {

    $requestStartTime = 0;
    if ($this->isDebug()) {
      $requestStartTime = floor(microtime(TRUE) * 1000);
    }

    $cache = $searchParams;
    unset($cache['lookfor']);
    // Recursevily implode & sha1 string to be used as standard length
    // key for cache.
    $cacheKey = sha1(self::recursiveImplode('-', $cache, TRUE, TRUE));

    if ($this->useCache && $this->isCached($cacheKey)) {
      return $this->getFromCache($cacheKey);
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
      return [];
    }

    if ($this->useCache) {
      /** @var \Drupal\helfi_atv\AtvDocument $document */
      foreach ($responseData['results'] as $document) {
        $cacheParams = [
          'transaction_id' => $document->getTransactionId(),
        ];
        $cacheKey2 = sha1(self::recursiveImplode('-', $cacheParams, TRUE, TRUE));
        $this->setToCache($cacheKey2, [$document]);
      }
      $this->setToCache($cacheKey, $responseData['results']);
    }

    if ($this->isDebug()) {
      $requestEndTime = floor(microtime(TRUE) * 1000);
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
   *   User documents' public data
   *
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
      $this->buildUrl('userdocuments/' . $sub . '/', $params),
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

    // It seems that something adds ending slash on platta
    // this will make sure theres only one slash.
    if (str_ends_with($this->baseUrl, '/')) {
      $newUrl = $this->baseUrl . $this->atvVersion . '/' . $endpoint;
    }
    else {
      $newUrl = $this->baseUrl . '/' . $this->atvVersion . '/' . $endpoint;
    }

    if (!empty($params)) {
      $paramCounter = 1;

      // If we have lookfor as an array, we need to parse parameters from
      // array to lookfor element.
      if (isset($params['lookfor']) && is_array($params['lookfor'])) {
        if ($paramCounter == 1) {
          $newUrl .= '?lookfor=';
        }
        else {
          $newUrl .= '&lookfor=';
        }
        // Since lookfor is single parameter, just add 1.
        $paramCounter++;
        // Parse lookfor parameter from array and separate key:value
        // pairs with comma.
        foreach ($params['lookfor'] as $key => $value) {
          $newUrl .= $key . ':' . $value . ',';
        }
        // Remove last comma from url.
        $newUrl = substr_replace($newUrl, "", -1);
        // And unset processed lookfor
        // if this is not an array, it just gets parsed with other attributes.
        unset($params['lookfor']);
      }

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
   * @throws \GuzzleHttp\Exception\GuzzleException|\Drupal\helfi_helsinki_profiili\TokenExpiredException
   */
  public function getDocument(string $id, bool $refetch = FALSE): AtvDocument {
    if (($this->useCache && $refetch === FALSE) && $this->isCached($id)) {
      return $this->getFromCache($id);
    }

    $response = $this->doRequest(
      'GET',
      $this->buildUrl('documents/' . $id),
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
        array_walk_recursive(
          $value,
          function (&$item) {
            if (is_string($item)) {
              $item = Xss::filter($item);
            }
          }
        );
        $contents = Json::encode($value);
      }
      else {
        $contents = Xss::filter($value);
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
      $this->setToCache(sha1($dataArray["transaction_id"]), [$updatedDocument]);
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
   * @throws \Drupal\helfi_helsinki_profiili\TokenExpiredException
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
   * @throws \GuzzleHttp\Exception\GuzzleException|\Drupal\helfi_helsinki_profiili\TokenExpiredException
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
   * Delete document attachment from ATV.
   *
   * @param string $attachmentUrl
   *   Url of an attachment.
   *
   * @return array|bool|\Drupal\file\FileInterface|\Drupal\helfi_atv\AtvDocument
   *   If removal succeeed.
   *
   * @throws \Drupal\helfi_atv\AtvAuthFailedException
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \Drupal\helfi_helsinki_profiili\TokenExpiredException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteAttachmentByUrl(string $attachmentUrl): AtvDocument|bool|array|FileInterface {

    $this->setAuthHeaders();

    return $this->doRequest(
      'DELETE',
      $attachmentUrl,
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
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function uploadAttachment(string $documentId, string $filename, File $file): mixed {

    try {
      $this->setAuthHeaders();
    }
    catch (AtvAuthFailedException | TokenExpiredException $e) {
      $this->logger->error(
        'File upload failed with error: @error',
        ['@error' => $e->getMessage()]
          );
      return FALSE;
    }

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
        $this->buildUrl('documents/' . $documentId . '/attachments'),
        [
          'headers' => $headers,
          'multipart' => [$data],
        ]
      );

    }
    catch (AtvAuthFailedException | TokenExpiredException $e) {
      $this->logger->error(
        'File upload failed with error: @error',
        ['@error' => $e->getMessage()]
          );
    }

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

    $this->debugPrint('request-> called');

    $requestStartTime = 0;
    if ($this->isDebug()) {
      $requestStartTime = floor(microtime(TRUE) * 1000);
      ;
    }
    $resp = $this->httpClient->request(
      $method,
      $url,
      $options
    );

    if ($this->isDebug()) {
      $requestEndTime = floor(microtime(TRUE) * 1000);
      ;
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
      }
      catch (EntityStorageException $e) {
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

    if (isset($bodyContents['count']) && $bodyContents['count'] !== count($bodyContents['results'])) {
      $bodyContents['results'] = array_merge($bodyContents['results'] ?? [], $prevRes);
      // Merge new results with old ones.
      if (isset($bodyContents['next']) && !empty($bodyContents['next'])) {
        // Replace hostname if we are running in local environment.
        if (str_contains(strtolower($this->appEnvironment), "local")) {
          $bodyContents['next'] = str_replace(".apps.", ".agw.", $bodyContents['next']);
        }
        if ($this->callCount < $this->maxPages) {
          // Call self for next results.
          $this->callCount++;
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
   * @param bool $apiKeyAuth
   *   Use apikey.
   *
   * @return array|bool|FileInterface|AtvDocument
   *   Content or boolean if void.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \Drupal\helfi_helsinki_profiili\TokenExpiredException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function doRequest(
    string $method,
    string $url,
    array $options,
    bool $apiKeyAuth = FALSE
  ): array|AtvDocument|bool|FileInterface {
    try {

      $this->debugPrint('doRequest->');

      if ($apiKeyAuth) {
        $this->debugPrint('doRequest-> use api key');
        // Set headers from configs.
        $this->setAuthHeaders(TRUE);

      }
      // If we don't have Authorization headers, we need to get them.
      elseif (empty($options['headers'])) {
        $this->debugPrint('doRequest-> Headers empty, try set them');
        // Set headers from configs.
        $this->setAuthHeaders();

        $this->debugPrint('doRequest-> Headers empty, after setAuthHeaders call. @headers', ['@headers' => Json::encode($this->headers)]);

        // If we have others, but auth is missing. let's override them only.
        if (!empty($this->headers['Authorization'])) {
          $this->debugPrint('doRequest-> Authorization headers set');
          $options['headers']['Authorization'] = $this->headers['Authorization'];
        }
        // If we have X-APi-Key, then use it.
        if (!empty($this->headers['X-Api-Key'])) {
          $this->debugPrint('doRequest-> X-Api-Key headers set');
          $options['headers']['X-Api-Key'] = $this->headers['X-Api-Key'];
        }
      }

      $responseContent = $this->request(
        $method,
        $url,
        $options
      );

      /** @var \GuzzleHttp\Psr7\Response */
      $response = $responseContent['response'];

      // ATV return 204 when deleting stuff.
      if ($method == 'DELETE') {
        if ($response->getStatusCode() == 204) {
          return TRUE;
        }
        return FALSE;
      }

      // @todo Check if there's any point doing these if's here?!?!
      if ($response->getStatusCode() == 200) {

        // If we have response content as attachment, let's just return that.
        if (isset($responseContent['data'])) {
          return $responseContent['data'];
        }
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
    }
    catch (ServerException | GuzzleException $e) {

      $msg = $e->getMessage();
      $this->logger->error($msg);
      $this->dispatchExceptionEvent($e);

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
    catch (AtvAuthFailedException $e) {
      $this->dispatchExceptionEvent($e);
    }
    catch (TokenExpiredException $e) {
      $this->dispatchExceptionEvent($e);
      /** @var \Drupal\helfi_helsinki_profiili\TokenExpiredException $e */
      throw $e;
    }
    return FALSE;
  }

  /**
   * Whether or not we have made this query?
   *
   * @param string $key
   *   Used key for caching.
   */
  public function clearCache(string $key = ''): void {
    $this->requestCache = [];
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
    return isset($this->requestCache[$key]);
  }

  /**
   * Get item from cache.
   *
   * @param string $key
   *   Key to fetch from tempstore.
   *
   * @return array|null
   *   Data in cache or null
   */
  protected function getFromCache(string $key): ?array {
    return $this->requestCache[$key] ?? NULL;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param mixed $data
   *   Cached data.
   */
  protected function setToCache(string $key, mixed $data) {
    $this->requestCache[$key] = $data;
  }

  /**
   * Is debug enebled.
   *
   * @return bool
   *   Debug true/false
   */
  public function isDebug(): bool {
    return $this->debug;
  }

  /**
   * Set debug value.
   *
   * @param bool $debug
   *   Debug true/false.
   */
  public function setDebug(bool $debug): void {
    $this->debug = $debug;
  }

  /**
   * Get baseurl for ATV.
   *
   * @return string
   *   ATV base url.
   */
  public function getBaseUrl(): string {
    return $this->baseUrl;
  }

  /**
   * Get users GDPR data from ATV.
   *
   * @param string $userId
   *   Whose data we're looking.
   * @param string|null $token
   *   Use token from request.
   *
   * @return array|bool|FileInterface|AtvDocument
   *   User's data or empty values.
   *
   * @throws AtvAuthFailedException
   * @throws AtvDocumentNotFoundException
   * @throws AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\helfi_helsinki_profiili\TokenExpiredException
   */
  public function getGdprData(string $userId, string $token = NULL): AtvDocument|bool|array|FileInterface {
    $this->setAuthHeaders(TRUE);

    return $this->doRequest(
      'GET',
      $this->buildUrl('gdpr-api/' . $userId,),
      [
        'headers' => $this->headers,
      ]
    );
  }

  /**
   * Delete users GDPR data from ATV.
   *
   * @param string $userId
   *   Whose data we're looking.
   * @param string|null $token
   *   Use token from request.
   *
   * @return array|bool|FileInterface|AtvDocument
   *   User's data or empty values.
   *
   * @throws AtvAuthFailedException
   * @throws AtvDocumentNotFoundException
   * @throws AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\helfi_helsinki_profiili\TokenExpiredException
   */
  public function deleteGdprData(string $userId, string $token = NULL): AtvDocument|bool|array|FileInterface {
    $this->setAuthHeaders(TRUE);

    return $this->doRequest(
      'DELETE',
      $this->buildUrl('gdpr-api/' . $userId),
      [
        'headers' => $this->headers,
      ]
    );
  }

  /**
   * Print debug messages.
   *
   * @param string $message
   *   Message.
   * @param array $replacements
   *   Replacements.
   */
  public function debugPrint(string $message, array $replacements = []): void {
    if ($this->isDebug()) {
      $this->logger->debug($message, $replacements);
    }
  }

  /**
   * Dispatches exception event.
   *
   * @param \Exception $exception
   *   The exception.
   */
  private function dispatchExceptionEvent(\Exception $exception): void {
    $event = new AtvServiceExceptionEvent($exception);
    $this->eventDispatcher->dispatch($event, AtvServiceExceptionEvent::EVENT_ID);
  }

}
