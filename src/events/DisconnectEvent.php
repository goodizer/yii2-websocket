<?php

namespace goodizer\websocket\events;

use yii\base\Event;
use Workerman\Connection\TcpConnection;
use goodizer\websocket\Server;

/**
 * Class DisconnectEvent
 *
 * @property Server $sender
 *
 * @package goodizer\websocket\events
 */
class DisconnectEvent extends Event
{
    /**
     * @var TcpConnection
     */
    public $connection;
}
