<?php

namespace goodizer\websocket\commands;

use goodizer\websocket\helpers\StatHelper;
use goodizer\websocket\events\MessageEvent;
use goodizer\websocket\events\ConnectEvent;
use goodizer\websocket\events\DisconnectEvent;
use goodizer\websocket\events\ErrorEvent;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\helpers\Console;
use yii\helpers\Json;

/**
 * Class Commands
 * @package goodizer\websocket
 */
abstract class Commands implements CommandsInterface
{
  /**
   * @param ConnectEvent $event
   * @return void
   */
  public function onConnect(ConnectEvent $event)
  {
    //Do something on new connection
  }

  /**
   * @param ErrorEvent $event
   * @return mixed|void
   * @throws \Exception
   */
  public function onError(ErrorEvent $event)
  {
    $class = get_class($event->exception);
    $type = lcfirst(($pos = strrpos($class, '\\')) ? substr($class, $pos + 1) : $class);

    $event->sender->sendMessage([
      'Exception' => [
        'type' => $type,
        'code' => $event->exception->getCode(),
        'message' => $event->exception->getMessage(),
        'trace' => YII_ENV_PROD
          ? ('#1 ' . $event->exception->getFile() . '(' . $event->exception->getLine() . ')')
          : $event->exception->getTraceAsString(),
        'sys_usage' => StatHelper::getLoads(),
      ]
    ], [
      'connectionId' => intval($event->connection),
    ], $event->connection);
  }

  /**
   * @param DisconnectEvent $event
   * @return void
   */
  public function onDisconnect(DisconnectEvent $event)
  {
    //Do something when client disconnected
  }

  /**
   * @param MessageEvent $event
   * @return void
   * @throws \Exception
   */
  public function onMessage(MessageEvent $event)
  {
    [$data, $params] = $this->runAndGetArgs($event);

    if (!empty($data)) {
      $event->sender->sendMessage($data, !$params ? [] : $params, $event->connection);
    }

    if (isset($event->receivedData['callback'])) {
      $event->sender->sendMessage(['callback' => $event->receivedData['callback']], [
        'connectionId' => intval($event->connection),
      ], $event->connection);
    }
  }

  /**
   * @param Event|MessageEvent $event
   * @return mixed|null
   */
  protected function runAndGetArgs($event)
  {
    $data = $event->receivedData;

    if (!isset($data['method'])) {
      throw new InvalidArgumentException("Message must contain a 'method'", 405);
    }

    $method = 'cmd' . ucfirst($data['method']);

    if (!method_exists($this, $method)) {
      throw new InvalidArgumentException("Method '{$method}' not found", 405);
    }

    $params = array_values($data['params'] ?? []);

    array_unshift($params, $event);
    array_walk($data['params'], function (&$v, $k) {
      $v = is_numeric($k) ? Json::encode($v) : ("{$k}: " . Json::encode($v));
    });
    $event->sender->log(
      "- method: [{$data['method']}]; args: [" . implode(', ', $data['params']) . "];",
      [Console::FG_CYAN, Console::CONCEALED],
      $event->connection
    );

    $sendArgs = call_user_func([$this, $method], ...$params);

    if (!is_array($sendArgs))
      $sendArgs = [$sendArgs];

    return array_pad($sendArgs, 2, []);
  }
}
