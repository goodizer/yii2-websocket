<?php

namespace goodizer\websocket;

use Yii;
use yii\base\Event;
use yii\base\Exception;
use goodizer\websocket\events\MessageEvent;
use goodizer\websocket\events\ConnectEvent;
use goodizer\websocket\events\DisconnectEvent;
use goodizer\websocket\events\ErrorEvent;

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
     * @return void
     */
    public function onError(ErrorEvent $event)
    {
        //Do something when an error occurred
    }

    /**
     * @param DisconnectEvent $event
     * @return void
     */
    public function onDisconnect(DisconnectEvent $event)
    {
        $connectionId = intval($event->connection->getSocket());
        if (isset($event->sender->clients[$connectionId])) {
            unset($event->sender->clients[$connectionId]);
        }

        //Do something when client disconnected
    }

    /**
     * @param MessageEvent $event
     * @return void
     * @throws \Exception
     */
    public function onMessage(MessageEvent $event)
    {
        \Yii::$app->db->open();
        $arguments = $this->_runAndGetArgs($event);
        \Yii::$app->db->close();

        if (!empty($arguments)) {
            call_user_func_array([$event->sender, 'sendMessage'], $arguments);
        }

        if (isset($event->receivedData['callback'])) {
            $event->sender->sendMessage(['callback' => $event->receivedData['callback']], [
                'connectionId' => intval($event->connection->getSocket()),
            ]);
        }
    }

    /**
     * @param Event|MessageEvent $event
     * @return mixed|null
     */
    protected function _runAndGetArgs($event)
    {
        $params = null;
        $data = $event->receivedData;

        if (isset($data['method'])) {
            array_unshift($data['params'], $event); //Putting Event object to the beginning of the  arguments array
            $method = ucfirst($data['method']);
            $params = call_user_func_array([$this, "cmd{$method}"], $data['params'] ?? []);
        }

        return $params;
    }
}
