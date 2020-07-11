<?php

namespace goodizer\websocket\events;

use yii\base\Event;
use Workerman\Connection\TcpConnection;
use goodizer\websocket\Server;

/**
 * Class ConnectEvent
 *
 * @property Server $sender
 *
 * @package goodizer\websocket\events
 */
class ConnectEvent extends Event
{
    /**
     * @var TcpConnection
     */
    public $connection;

    /**
     * @var array
     */
    public $headers = [];
}
