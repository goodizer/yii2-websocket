<?php

namespace goodizer\websocket\exceptions;

use yii\base\Exception;

/**
 * Class UserAccessException
 * @package goodizer\websocket\exceptions
 */
class UserAccessException extends Exception
{
  /**
   * @return string the user-friendly name of this exception
   */
  public function getName()
  {
    return 'UserAccessException';
  }

}
