<?php

namespace Drupal\helfi_atv;

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
   * Constructs an AtvService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;

    $this->headers = [
      'X-Api-Key' => getenv('ATV_API_KEY'),
    ];

    // @todo figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');

  }

  /**
   * Search documents with given arguments.
   *
   * @param array $searchParams
   *   Search params.
   *
   * @return array
   *   Data
   */
  public function searchDocuments(array $searchParams): array {

    $resp = $this->request(
      'GET',
      $this->buildUrl($searchParams),
      [
        'headers' => $this->headers,
      ]
    );

    $responseData = JSON::decode($resp);

    // if no data for some reason, don't fail, return empty array instead.
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
   * List documents for this user.
   */
  public function listDocuments() {

  }

  /**
   * Fetch single document with id.
   *
   * @param string $id
   *   Document id.
   */
  public function getDocument(string $id) {

  }

  /**
   * Save new document.
   */
  public function postDocument() {

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
   */
  public function patchDocument(string $id, array $document): ?bool {
    $patchUrl = $this->baseUrl . $id;

    $content = JSON::encode((object) $document);

    return $this->request(
      'PATCH',
      $patchUrl,
      [
        'headers' => $this->headers,
        'body' => $content,
      ]
    );
  }

  /**
   * Get document attachements.
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
   */
  private function request(string $method, string $url, array $options): bool|array {

    try {
      $resp = $this->httpClient->request(
        $method,
        $url,
        $options
      );
      if ($resp->getStatusCode() == 200) {
        if ($method == 'GET') {
          return $resp->getBody()->getContents();
        }
        else {
          return TRUE;
        }
      }
      return FALSE;
    }
    catch (ServerException | GuzzleException $e) {
      // @todo error handler for ATV request
      return FALSE;
    }
  }

}
