<?php

namespace goodizer\websocket\events;

/**
 * Class MessageEvent
 *
 * @package goodizer\websocket\events
 */
class MessageEvent extends Event
{
  /**
   * @var array
   */
  public $receivedData;

  /**
   * @var int
   */
  public $status;
}
