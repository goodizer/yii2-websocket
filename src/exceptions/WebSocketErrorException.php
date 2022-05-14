<?php

namespace goodizer\websocket\exceptions;

use yii\base\Exception;

/**
 * Class WebSocketErrorException
 * @package goodizer\websocket\exceptions
 */
class WebSocketErrorException extends Exception
{
  /**
   * @return string the user-friendly name of this exception
   */
  public function getName()
  {
    return 'WebSocketErrorException';
  }

}
