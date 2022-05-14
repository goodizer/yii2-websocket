<?php

namespace goodizer\websocket\events;

/**
 * Class ErrorEvent
 *
 * @package goodizer\websocket\events
 */
class ErrorEvent extends Event
{
    /**
     * @var \Exception
     */
    public $exception;
}
