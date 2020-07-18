<?php

namespace goodizer\websocket;

use Yii;
use yii\base\Event;
use yii\helpers\Json;
use yii\base\Component;
use Exception;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use goodizer\websocket\events\ConnectEvent;
use goodizer\websocket\events\DisconnectEvent;
use goodizer\websocket\events\MessageEvent;
use goodizer\websocket\events\ErrorEvent;

/**
 * Class Server
 * @package app\components\yii2\websocket
 */
class Server extends Component
{
    const EVENT_CONNECT = 'gdr-ws-connect';
    const EVENT_DISCONNECT = 'gdr-ws-disconnect';
    const EVENT_MESSAGE = 'gdr-ws-message';
    const EVENT_ERROR = 'gdr-ws-error';

    /**
     * @var Worker
     */
    protected $worker;

    /**
     * Class with methods which handle received data and return handled data for send to connections
     *
     * @var string
     */
    public $commandClass;

    /**
     * @var string
     */
    public $host = 'localhost';

    /**
     * @var int
     */
    public $port = 8000;

    /**
     * @var bool
     */
    public $isSecure = true;

    /**
     * @var string
     */
    public $localCert;

    /**
     * @var string
     */
    public $localPk;

    /**
     * @var bool
     */
    public $daemonMode = false;

    /**
     * @var bool
     */
    public $reloadOnError = false;

    /**
     * @var array The registration users ids for connections
     */
    public $clients = [];

    /**
     * Init properties, Yii events and Worker events
     */
    public function init()
    {
        parent::init();

        $command = new $this->commandClass();

        Event::on(static::class, static::EVENT_CONNECT, [$command, 'onConnect']);
        Event::on(static::class, static::EVENT_DISCONNECT, [$command, 'onDisconnect']);
        Event::on(static::class, static::EVENT_MESSAGE, [$command, 'onMessage']);
        Event::on(static::class, static::EVENT_ERROR, [$command, 'onError']);

        $context = [];

        if ($this->isSecure && $this->localCert && $this->localPk) {
            $context = [
                'ssl' => [
                    'local_cert' => $this->localCert,
                    'local_pk' => $this->localPk,
                    'verify_peer' => false,
                ]
            ];
        }

        // Create a Websocket server
        $this->worker = new Worker("websocket://{$this->host}:{$this->port}", $context);
        // 4 processes
        $this->worker->count = 4;

        if ($this->isSecure) {
            $this->worker->transport = 'ssl';
        }

        // Emitted when client is connected
        $this->worker->onWebSocketConnect  = function (TcpConnection $connection, $rawHeaders) {
            $headers = [];

            foreach (preg_split("/\r\n/", rtrim($rawHeaders)) as $line) {
                $line = chop($line);
                if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                    $headers[trim($matches[1])] = trim($matches[2]);
                }
            }

            Event::trigger(Yii::$app->get('websocketServer'), static::EVENT_CONNECT, new ConnectEvent([
                'connection' => $connection,
                'headers' => $headers,
            ]));
        };

        // Emitted when connection closed
        $this->worker->onClose = function (TcpConnection $connection) {
            Event::trigger(Yii::$app->get('websocketServer'), static::EVENT_DISCONNECT, new DisconnectEvent([
                'connection' => $connection,
            ]));
        };

        // Emitted when data received
        $this->worker->onMessage = function (TcpConnection $connection, $data) {
            try {
                Event::trigger(Yii::$app->get('websocketServer'), static::EVENT_MESSAGE, new MessageEvent([
                    'connection' => $connection,
                    'receivedData' => Json::decode($data),
                ]));
            } catch (Exception $e) {
                $this->reloadWorker($e);
            }
        };

        $this->worker->onError = function ($connection, $code, $message) {
            Event::trigger(Yii::$app->get('websocketServer'), static::EVENT_ERROR, new ErrorEvent([
                'connection' => $connection,
                'code' => $code,
                'message' => $message,
            ]));
        };
    }

    /**
     * Run workers
     *
     * @throws Exception
     */
    public function start()
    {
        if (static::isLinux()) {
            //Replace command Workerman arguments for Linux systems
            global $argv;

            $argv[0] = $argv[1];
            $argv[1] = 'start';

            if ($this->daemonMode) {
                $argv[2] = '-d';
            }
        } else {
            if ($this->daemonMode) {
                Worker::$daemonize = true;
            }
        }

        try {
            Worker::runAll();
        } catch (Exception $e) {
            $this->stopOrReloadWorker($e);
        }
    }

    /**
     * Stop workers
     */
    public function stop()
    {
        if (static::isLinux()) {
            //Replace command Workerman arguments for Linux systems
            global $argv;

            $argv[0] = $argv[1];
            $argv[1] = 'stop';
        }

        Worker::stopAll();
    }

    /**
     * @param array $msg Сообщение
     * @param array $params Options of a target for sending message
     * @throws Exception
     */
    public function sendMessage(array $msg, array $params = [])
    {
        $params = $params + [
                'connectionId' => null,
                'exceptConnectionId' => null,
                'clientIds' => [],
                'exceptClientIds' => [],
            ];

        $msg = json_encode($msg);

        foreach ($this->worker->connections as $connection) {
            /** @var TcpConnection $connection */
            $cid = intval($connection->getSocket());

            // Sent message by connection ID
            if ($params['connectionId'] !== null && $params['connectionId'] != $cid)
                continue;

            // Skip by connection ID if exceptConnectionId is specified
            if ($params['exceptConnectionId'] !== null && $params['exceptConnectionId'] == $cid)
                continue;

            if (isset($this->clients[$cid])) {
                // Send message to connection by specified client ID
                if (!empty($params['clientIds']) && !in_array($this->clients[$cid], $params['clientIds']))
                    continue;
                // Skip by client ID if exceptClientIds is specified
                if (!empty($params['exceptClientIds']) && in_array($this->clients[$cid], $params['exceptClientIds']))
                    continue;
            }

            // Send the message to the recipient
            $connection->send($msg);
        }
    }

    /**
     * @param Exception $exception
     * @throws Exception
     */
    protected function stopOrReloadWorker($exception)
    {
        if (!$this->reloadOnError) {
            Worker::stopAll();

            throw $exception;
        }

        print_r([
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            //'trace' => $e->getTraceAsString(),
        ]);
        echo "\r\n\r\nReload all workers...\r\n";

        Worker::reloadAllWorkers();
    }

    /**
     * Function to check operating system
     *
     * @return bool
     */
    public static function isLinux()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'LIN')
            return true;
        else
            return false;
    }
}
