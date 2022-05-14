<?php

namespace goodizer\websocket\events;

use goodizer\websocket\App;
use Ratchet\ConnectionInterface;
use yii\base\Event as BaseEvent;

/**
 * Class Event
 *
 * @property App $sender
 * @property int $connection
 *
 * @package goodizer\websocket\events
 */
class Event extends BaseEvent
{
  /**
   * @var mixed|ConnectionInterface
   */
  public $from;

  /**
   * @return mixed
   */
  public function getConnection()
  {
    return $this->from->resourceId;
  }

  /**
   * @param mixed|ConnectionInterface $conn
   */
  public function setConnection(ConnectionInterface $conn)
  {
    $this->from = $conn;
  }
}
