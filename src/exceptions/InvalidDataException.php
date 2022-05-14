<?php

namespace goodizer\websocket\exceptions;

use yii\base\Exception;
use yii\db\ActiveRecord;

/**
 * Class InvalidDataException
 * @package goodizer\websocket\exceptions
 */
class InvalidDataException extends Exception
{
  /**
   * @return string the user-friendly name of this exception
   */
  public function getName()
  {
    return 'InvalidDataException';
  }

  /**
   * InvalidDataException constructor.
   * @param ActiveRecord $model
   * @param string $message
   * @param int $code
   * @param \Exception|null $previous
   */
  public function __construct($model, $message = '', $code = 0, \Exception $previous = null)
  {
    if ($model->hasErrors()) {
      $message .= "\n";
      foreach ($model->getErrors() as $field => $errors) {
        $message .= implode(";\n", $errors);
      }
    }

    parent::__construct($message, $code, $previous);
  }

}
