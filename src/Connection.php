<?php

namespace goodizer\websocket;

use goodizer\websocket\exceptions\UserAccessException;

/**
 * Class Connection
 * @package goodizer\websocket
 */
class Connection
{
  public $resourceId;
  public $isRegistered = false;
  public $userId;
  public $closing = false;

  /**
   * Connection constructor.
   * @param int $resourceId
   * @param int|null $userId
   */
  public function __construct($resourceId, $userId = null)
  {
    $this->resourceId = $resourceId;

    if ($userId) {
      $this->registerUser($userId);
    }
  }

  /**
   * @return string the user-friendly name of this exception
   */
  public function getId()
  {
    return $this->resourceId;
  }

  /**
   * @param $userId
   * @throws UserAccessException
   */
  public function registerUser($userId)
  {
    if ($this->isRegistered === true) {
      throw new UserAccessException("User #[{$userId}] already has been registered, [{$this->resourceId}]");
    }

    $this->userId = (int)$userId;
    $this->isRegistered = true;
  }

  /**
   * @param $userId
   * @throws UserAccessException
   */
  public function unregisterUser($userId)
  {
    if (!$this->resourceId) {
      throw new UserAccessException("User #[{$userId}] resourceId prop not set, unable to unregister, [{$this->resourceId}]");
    }

    if ($this->isRegistered === false) {
      throw new UserAccessException("User #[{$userId}] is not registered, unable to unregister, [{$this->resourceId}]");
    }

    if ($this->userId === (int)$userId) {
      $this->userId = null;
      $this->isRegistered = false;
      $this->closing = true;
    }
  }

  /**
   * @param callable $callback
   * @param \SplObjectStorage $clients
   * @param mixed $until
   * @return array
   */
  public static function mapCallback(callable $callback, \SplObjectStorage $clients, $until = -1): array
  {
    $items = [];
    $clients->rewind();
    while ($clients->valid()) {
      $object = $clients->current(); // similar to current($s)
      $data = $clients->getInfo();
      $clients->next();

      $r = $callback($data, $object);
      if ($until !== -1 && $until === $r) {
        $clients->rewind();
        return array_filter($items);
      }

      $items[] = $r;
    }

    return array_filter($items);
  }
}
