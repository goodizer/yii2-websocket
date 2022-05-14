<?php

namespace goodizer\websocket\exceptions;

use yii\base\Exception;

/**
 * Class WebSocketSendException
 * @package goodizer\websocket\exceptions
 */
class WebSocketSendException extends Exception
{
  /**
   * @return string the user-friendly name of this exception
   */
  public function getName()
  {
    return 'WebSocketSendException';
  }

}
