<?php

namespace goodizer\websocket\exceptions;

use yii\base\Exception;

/**
 * Class WebSocketHandshakeException
 * @package goodizer\websocket\exceptions
 */
class WebSocketHandshakeException extends Exception
{
  /**
   * @return string the user-friendly name of this exception
   */
  public function getName()
  {
    return 'WebSocketHandshakeException';
  }

}
