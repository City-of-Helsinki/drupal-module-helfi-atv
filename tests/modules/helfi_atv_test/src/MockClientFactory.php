<?php

namespace Drupal\helfi_atv_test;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Site\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * Helper class to construct a mock HTTP client with Drupal specific config.
 *
 * This is mostly copy of the core file.
 */
class MockClientFactory {

  /**
   * The handler stack.
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $stack;

  /**
   * The mock handler.
   *
   * @var \GuzzleHttp\Handler\MockHandler
   */
  protected $mockHandler;

  /**
   * History.
   *
   * @var array
   */
  protected $historyContainer;

  /**
   * Constructs a new ClientFactory instance.
   */
  public function __construct() {
    // Create a mockhandler.
    $mock = new MockHandler([]);
    $this->mockHandler = $mock;
    // Create handler stack.
    $handlerStack = HandlerStack::create($this->mockHandler);
    $this->stack = $handlerStack;
    $this->historyContainer = [];
    $history = Middleware::history($this->historyContainer);
    $handlerStack->push($history);
  }

  /**
   * Add new response for mock handler.
   *
   * @param Response|ResponseException $response
   *   The response or response exception.
   */
  public function addResponse($response): void {
    $this->mockHandler->append($response);
  }

  /**
   * Get headers from a request.
   *
   * @param int $index
   *   Request index.
   */
  public function getHeaders($index = 0): array {
    return $this->historyContainer[$index]['request']->getHeaders();
  }

  /**
   * Get request URL.
   *
   * @param int $index
   *   Response index.
   */
  public function getRequestUrl($index = 0): string {
    return (string) $this->historyContainer[$index]['request']->getUri();
  }

  /**
   * Get request.
   *
   * @param int $index
   *   Response index.
   */
  public function getRequest($index = 0) {
    return $this->historyContainer[$index]['request'];
  }

  /**
   * Constructs a new client object from some configuration.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  public function fromOptions(array $config = []) {
    $default_config = [
      // Security consideration: we must not use the certificate authority
      // file shipped with Guzzle because it can easily get outdated if a
      // certificate authority is hacked. Instead, we rely on the certificate
      // authority file provided by the operating system which is more likely
      // going to be updated in a timely fashion. This overrides the default
      // path to the pem file bundled with Guzzle.
      'verify' => TRUE,
      'timeout' => 30,
      'headers' => [
        'User-Agent' => 'Drupal/' . \Drupal::VERSION . ' (+https://www.drupal.org/) ' . \GuzzleHttp\default_user_agent(),
      ],
      'handler' => $this->stack,
      // Security consideration: prevent Guzzle from using environment variables
      // to configure the outbound proxy.
      'proxy' => [
        'http' => NULL,
        'https' => NULL,
        'no' => [],
      ],
    ];

    $config = NestedArray::mergeDeep($default_config, Settings::get('http_client_config', []), $config);
    return new Client($config);
  }

}
