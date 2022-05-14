<?php

namespace goodizer\websocket\exceptions;

use yii\base\Exception;

/**
 * Class NotFoundException
 * @package goodizer\websocket\exceptions
 */
class NotFoundException extends Exception
{
  /**
   * @return string the user-friendly name of this exception
   */
  public function getName()
  {
    return 'NotFoundException';
  }

}
