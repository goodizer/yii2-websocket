<?php

namespace goodizer\websocket;

use Yii;
use React\EventLoop\Factory;
use Ratchet\Client\Connector as ClientConnector;
use React\Socket\Connector as SocketConnector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use yii\helpers\Json;

/**
 * Class Client
 * @package goodizer\websocket
 */
class Client
{
  protected $loop;
  protected $socket_uri;

  protected $request;
  protected $response;
  protected $awaitForResponse = false;

  public $timeout = 2;
  public $dns = '8.8.8.8';
  public $headers = [];

  /**
   * Client constructor.
   * @param $uri
   * @param array $config
   */
  public function __construct($uri, $config = [])
  {
    $this->socket_uri = $uri;
    Yii::configure($this, $config);
    $this->headers['User-Agent'] = 'websocket-client-php';

    $this->loop = Factory::create();
    $reactConnector = new SocketConnector($this->loop, [
      'dns' => $this->dns,
      'timeout' => $this->timeout
    ]);
    $connector = new ClientConnector($this->loop, $reactConnector);
    $connector($this->socket_uri, [], $this->headers)
      ->then(function (WebSocket $conn) {
        if ($this->awaitForResponse) {
          $conn->on('message', function (MessageInterface $msg) use ($conn) {
            $this->response = Json::decode($msg);
            $conn->close();
          });
        }

        $conn->send($this->request);

        if (!$this->awaitForResponse) {
          $conn->close();
        }
      }, function (\Exception $e) {
        $this->loop->stop();
        throw $e;
      });
  }

  /**
   * @param mixed $request
   * @return bool
   */
  public function release($request)
  {
    $this->request = !is_string($request) ? Json::encode($request) : $request;
    $this->awaitForResponse = false;

    $this->loop->run();

    return true;
  }

  /**
   * @param mixed $request
   * @return array
   */
  public function receive($request)
  {
    $this->request = !is_string($request) ? Json::encode($request) : $request;
    $this->awaitForResponse = true;

    $this->loop->run();

    return $this->response;
  }
}
