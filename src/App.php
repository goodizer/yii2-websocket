<?php

namespace goodizer\websocket;

use goodizer\websocket\commands\IntervalInterface;
use goodizer\websocket\events\ConnectEvent;
use goodizer\websocket\events\DisconnectEvent;
use goodizer\websocket\events\ErrorEvent;
use goodizer\websocket\events\MessageEvent;
use goodizer\websocket\exceptions\UserAccessException;
use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\JsonException;
use Ratchet\WebSocket\WsConnection;
use Yii;
use yii\base\Component;
use yii\helpers\Console;
use yii\helpers\Json;

/**
 * Class App
 * @package goodizer\websocket
 */
class App extends Component implements MessageComponentInterface
{
  const EVENT_CONNECT = 'ws-connect';
  const EVENT_DISCONNECT = 'ws-disconnect';
  const EVENT_MESSAGE = 'ws-message';
  const EVENT_ERROR = 'ws-error';
  const EVENT_INTERVAL = 'ws-interval';

  /**
   * @var Connection[]|WsConnection[]|\SplObjectStorage
   */
  public $clients;

  /**
   * @var array
   */
  public $registers = [];

  /**
   * @var callable
   */
  public $authCallback;

  /**
   * @var string
   */
  public $id;

  /**
   * @var bool
   */
  public $enableLog = false;

  /**
   * App constructor.
   * @param string $class
   * @param array $config
   */
  public function __construct(string $class, array $config = [])
  {
    parent::__construct($config);

    $this->clients = new \SplObjectStorage;

    $command = new $class();

    $this->on(static::EVENT_CONNECT, [$command, 'onConnect']);
    $this->on(static::EVENT_DISCONNECT, [$command, 'onDisconnect']);
    $this->on(static::EVENT_MESSAGE, [$command, 'onMessage']);
    $this->on(static::EVENT_ERROR, [$command, 'onError']);

    if ($command instanceof IntervalInterface) {
      $this->on(static::EVENT_INTERVAL, [$command, 'onInterval']);
    }
  }

  /**
   * @param ConnectionInterface|mixed $conn
   */
  public function onOpen(ConnectionInterface $conn)
  {
    $this->log("");
    $this->log("onOpen;", [Console::FG_GREY, Console::BOLD], $conn->resourceId);
    $this->clients->attach($conn, new Connection($conn->resourceId));

    if (is_callable($this->authCallback)) {
      $this->log("- [authCallback] exec...", [Console::FG_CYAN, Console::CONCEALED], $conn->resourceId);

      try {
        $this->pingDbConnection($conn);

        if (!call_user_func($this->authCallback, $conn->httpRequest)) {
          throw new UserAccessException("Unauthorized. Access denied. Bye", 401);
        }

        $this->log(
          "- [authCallback]: ["
          . Console::ansiFormat("SUCCESS", [Console::FG_GREEN, Console::BOLD])
          . Console::ansiFormat("];", [Console::FG_CYAN, Console::CONCEALED]),
          [Console::FG_CYAN, Console::CONCEALED],
          $conn->resourceId
        );
      } catch (\Throwable $e) {
        $this->log(
          "- [authCallback]: ["
          . Console::ansiFormat("FAILED", [Console::FG_RED, Console::BOLD])
          . Console::ansiFormat("];", [Console::FG_CYAN, Console::CONCEALED]),
          [Console::FG_CYAN, Console::CONCEALED],
          $conn->resourceId
        );
        $this->onError($conn, $e);
        return;
      }
    }

    $this->trigger(static::EVENT_CONNECT, new ConnectEvent([
      'connection' => $conn,
    ]));
  }

  /**
   * @param ConnectionInterface|mixed $from
   * @param string $msg
   */
  public function onMessage(ConnectionInterface $from, $msg)
  {
    $this->log("onMessage;", [Console::FG_GREY, Console::BOLD], $from->resourceId);

    if (null === ($json = @json_decode($msg, true))) {
      throw new JsonException;
    }

    try {
      $this->pingDbConnection($from);
      $this->trigger(static::EVENT_MESSAGE, new MessageEvent([
        'connection' => $from,
        'receivedData' => $json,
      ]));
    } catch (\Exception $e) {
      $this->onError($from, $e);
    }
  }

  /**
   * @param ConnectionInterface|mixed $conn
   */
  public function onClose(ConnectionInterface $conn)
  {
    $this->log("onClose;\n", [Console::FG_GREY], $conn->resourceId);

    $this->trigger(static::EVENT_DISCONNECT, new DisconnectEvent([
      'connection' => $conn,
    ]));
    $this->clients->detach($conn);
  }

  /**
   * @param ConnectionInterface|mixed $conn
   * @param Exception $e
   */
  public function onError(ConnectionInterface $conn, $e)
  {
    $this->log("onError: [{$e->getMessage()}({$e->getFile()}:{$e->getLine()})];", [Console::FG_RED, Console::BOLD], $conn->resourceId);

    $this->trigger(static::EVENT_ERROR, new ErrorEvent([
      'connection' => $conn,
      'exception' => $e,
    ]));

    $this->clients->detach($conn);
    $conn->close();
  }

  /**
   * @param $data
   * @param array $params
   * @param int|null $currentResourceId
   * @return bool
   */
  public function sendMessage($data, $params = [], $currentResourceId = null)
  {
    $toAll = empty($params);
    $params = $params + [
        'connectionId' => null,
        'exceptConnectionId' => null,
        'recipientsIds' => [],
        'callback' => null,
      ];

    $msg = Json::encode($data);

    $this->clients->rewind();

    while ($this->clients->valid()) {
      /**
       * @var $conn ConnectionInterface
       * @var $client Connection
       */
      $conn = $this->clients->current();
      $client = $this->clients->getInfo();

      $this->clients->next();

      if (!$toAll) {
        // Skip sending message by specified connection ID
        if ($params['exceptConnectionId'] !== null && $params['exceptConnectionId'] == $client->resourceId) {
          continue;
        }

        // Sent message by connection ID
        if ($params['connectionId'] !== null && $params['connectionId'] != $client->resourceId) {
          continue;
        }

        // Send message to allowed connections
        if (!empty($params['recipientsIds'])) {
          if (!in_array($client->userId, $params['recipientsIds'])) {
            continue;
          }
        }

        if (is_callable($params['callback']) && $params['callback']($client) === false) {
          continue;
        }
      }

      //Send the message to the recipient
      $conn->send($msg);

      $this->log("- send to [{$client->resourceId}]" . ($conn->WebSocket->closing ? "(closing);" : ";") . "", [
        Console::FG_GREEN, Console::BOLD,
      ], $currentResourceId);
    }

    return true;
  }


  /**
   * @return int
   */
  public function getTotalOnline()
  {
    $ids = Connection::mapCallback(function (?Connection $client) {
      return $client->closing !== true ? $client->userId : null;
    }, $this->clients);

    return sizeof(array_keys(array_flip($ids)));
  }

  /**
   * @param $text
   * @param array $f
   * @param int|null $cid
   * @param bool $return
   * @return string|void
   */
  public function log($text, $f = [Console::FG_GREY, Console::BOLD], $cid = null, $return = false)
  {
    if (!$this->enableLog) return;
    $out = '';

    if (empty($text)) {
      $out = "\n";
    } else {
      if (!$return) {
        $now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
        $d = substr($now->format("Y-m-d H:i:s.u"), 0, -3);
        $l = strtoupper($this->id);
        $out .= Console::ansiFormat("[$d]", [Console::FG_GREY]);
        $out .= Console::ansiFormat("[$l]", [Console::FG_PURPLE, Console::BOLD]);
      }

      if ($cid) {
        $out .= Console::ansiFormat("[$cid]", [Console::FG_YELLOW, Console::BOLD]);
      }

      $out .= Console::ansiFormat(" $text\n", $f);
    }

    if ($return) {
      return $out;
    }

    echo $out;
  }

  /**
   * @param ConnectionInterface|mixed $conn
   * @throws \yii\db\Exception
   */
  public function pingDbConnection(ConnectionInterface $conn)
  {
    try {
      Yii::$app->db->open();
      Yii::$app->db->createCommand('SELECT 1')->execute();
    } catch (\PDOException | \yii\db\Exception $e) {
      $this->log("[" . get_class($e) . "]:\n{$e->getMessage()};", [Console::FG_RED, Console::BOLD], $conn->resourceId);
      Yii::$app->db->close();
      Yii::$app->db->open();
      $this->log("Reconnected to DB;", [Console::FG_GREEN, Console::BOLD], $conn->resourceId);
    }
  }
}
