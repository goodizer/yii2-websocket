<?php

namespace goodizer\websocket\extensions;

use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;
use React\Socket\SecureServer as SecureReactor;
use Ratchet\ComponentInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServerInterface;
use Ratchet\Http\OriginCheck;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\Server\IoServer;
use Ratchet\Server\FlashPolicy;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\WebSocket\MessageComponentInterface as WsMessageComponentInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Wamp\WampServer;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;

/**
 * An opinionated facade class to quickly and easily create a WebSocket server.
 * A few configuration assumptions are made and some best-practice security conventions are applied by default.
 */
class ServerWrapper
{
  /**
   * @var \Symfony\Component\Routing\RouteCollection
   */
  public $routes;

  /**
   * @var \Ratchet\Server\IoServer
   */
  public $flashServer;

  /**
   * @var \Ratchet\Server\IoServer
   */
  protected $_server;

  /**
   * The Host passed in construct used for same origin policy
   * @var string
   */
  protected $httpHost;

  /***
   * The port the socket is listening
   * @var int
   */
  protected $port;

  /**
   * @var int
   */
  protected $_routeCounter = 0;

  /**
   * ServerWrapper constructor.
   * @param string $httpHost
   * @param int $port
   * @param string $address
   * @param array $sslContext
   */
  public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1', array $sslContext = [])
  {
    if (extension_loaded('xdebug') && getenv('RATCHET_DISABLE_XDEBUG_WARN') === false) {
      trigger_error('XDebug extension detected. Remember to disable!', E_USER_WARNING);
    }

    $loop = LoopFactory::create();
    $this->httpHost = $httpHost;
    $this->port = $port;

    if (!empty($sslContext)) {
      $socket = new SecureReactor(new Reactor($address . ':' . $port, $loop), $loop, $sslContext);
    } else {
      $socket = new Reactor($address . ':' . $port, $loop);
    }

    $this->routes = new RouteCollection;
    $this->_server = new IoServer(new HttpServer(new Router(new UrlMatcher($this->routes, new RequestContext))), $socket, $loop);

    $policy = new FlashPolicy;
    $policy->addAllowedAccess($httpHost, 80);
    $policy->addAllowedAccess($httpHost, $port);

    $this->flashServer = new IoServer($policy, new Reactor(80 == $port ? '0.0.0.0:843' : 8843, $loop));
  }

  /**
   * @param $path
   * @param ComponentInterface $controller
   * @param array $allowedOrigins
   * @param null $httpHost
   * @return ComponentInterface|HttpServerInterface|OriginCheck|WsServer
   */
  public function route($path, ComponentInterface $controller, array $allowedOrigins = array(), $httpHost = null)
  {
    if ($controller instanceof HttpServerInterface || $controller instanceof WsServer) {
      $decorated = $controller;
    } elseif ($controller instanceof WampServerInterface) {
      $decorated = new WsServer(new WampServer($controller));
      $decorated->enableKeepAlive($this->_server->loop);
    } elseif ($controller instanceof MessageComponentInterface || $controller instanceof WsMessageComponentInterface) {
      $decorated = new WsServer($controller);
      $decorated->enableKeepAlive($this->_server->loop);
    } else {
      $decorated = $controller;
    }

    if ($httpHost === null) {
      $httpHost = $this->httpHost;
    }

    $allowedOrigins = array_values($allowedOrigins);
    if (0 === count($allowedOrigins)) {
      $allowedOrigins[] = $httpHost;
    }
    if ('*' !== $allowedOrigins[0]) {
      $decorated = new OriginCheck($decorated, $allowedOrigins);
    }

    //allow origins in flash policy server
    if (empty($this->flashServer) === false) {
      foreach ($allowedOrigins as $allowedOrigin) {
        $this->flashServer->app->addAllowedAccess($allowedOrigin, $this->port);
      }
    }

    $this->routes->add(
      'rr-' . ++$this->_routeCounter,
      new Route($path, ['_controller' => $decorated], ['Origin' => $this->httpHost], [], $httpHost, [], ['GET'])
    );

    return $decorated;
  }

  /**
   * Run the server by entering the event loop
   */
  public function run()
  {
    $this->_server->run();
  }

  /**
   * Stop server by entering the event loop
   */
  public function stop()
  {
    if (!$this->_server->loop) {
      throw new \RuntimeException("A React Loop was not provided during instantiation");
    }
    $this->_server->loop->stop();
  }
}
