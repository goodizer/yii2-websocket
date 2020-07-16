<?php

namespace goodizer\websocket\events;

use yii\base\Event;
use Workerman\Connection\TcpConnection;
use goodizer\websocket\Server;

/**
 * Class ErrorEvent
 *
 * @property Server $sender
 *
 * @package goodizer\websocket\events
 */
class ErrorEvent extends Event
{
    /**
     * @var TcpConnection
     */
    public $connection;

    /**
     * @var int
     */
    public $code;
    /**
     * @var string
     */
    public $message;
}
