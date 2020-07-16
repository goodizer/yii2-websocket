<?php

namespace goodizer\websocket;

use yii\base\Component;
use yii\helpers\Json;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

/**
 * Class Client
 * @package goodizer\websocket
 */
class Client extends Component
{
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
     * Init properties
     */
    public function init()
    {
        parent::init();

        $options = (object)[
            'host' => $this->host,
            'port' => $this->port,
            'isSecure' => $this->isSecure,
            'localCert' => $this->localCert,
            'localPk' => $this->localPk,
        ];

        // Create a Websocket server
        $worker = new Worker();
        // Emitted when new connection come
        $worker->onWorkerStart = function () use ($options, $worker) {
            $context = [];

            if ($options->isSecure && $options->localCert && $options->localPk) {
                $context = [
                    'ssl' => [
                        'local_cert' => $options->localCert,
                        'local_pk' => $options->localPk,
                        'verify_peer' => false,
                    ]
                ];
            }

            // Websocket protocol for client.
            $wsConnection = new AsyncTcpConnection("ws://{$this->host}:{$this->port}", $context);

            if ($options->isSecure) {
                $wsConnection->transport = 'ssl';
            }

            $wsConnection->onConnect = function (AsyncTcpConnection $connection) use($worker) {
                global $clientData;

                $connection->send(Json::encode($clientData));
                $worker->stop();
            };
            $wsConnection->onMessage = function ($connection, $data) {
                echo "onMessage: $data\r\n";
            };
            $wsConnection->onError = function ($connection, $code, $msg) {
                echo "onError: $msg\r\n";
            };
            $wsConnection->onClose = function ($connection) {
                echo "onClose: {$connection->getSocket()}\r\n\r\n";
            };
            $wsConnection->connect();
        };
    }

    /**
     * @param array $data
     */
    public function send($data = [])
    {
        global $clientData;

        $clientData = $data;

        Worker::runAll();
    }
}
