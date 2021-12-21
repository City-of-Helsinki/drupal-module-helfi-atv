<?php

namespace Drupal\helfi_atv;

use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;

/**
 * AtvService service.
 */
class AtvService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  protected array $headers;

  /**
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

    // TODO: figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');

  }

  /**
   * @param array $searchParams
   */
  public function searchDocuments(array $searchParams) {


    $resp = $this->httpClient->request('GET', $this->buildUrl($searchParams),[
      'headers' => $this->headers
    ]);

    $responseData = JSON::decode($resp->getBody()->getContents());

    return $responseData['results'];

  }

  private function buildUrl($params) {
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

  public function listDocuments() {

  }

  public function getDocument($id) {

  }

  public function postDocument() {

  }

  public function patchDocument($id,$document) {
    $patchUrl = $this->baseUrl . $id;

    $resp = $this->httpClient->request(
      'PATCH',
      $patchUrl,
      [
        'headers' => $this->headers,
        'body' => JSON::encode((object) $document),
      ]
    );

  }

  public function getAttachments() {

  }

  public function getAttachment($id) {

  }


}
