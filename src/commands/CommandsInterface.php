<?php

namespace goodizer\websocket\commands;

use goodizer\websocket\events\ConnectEvent;
use goodizer\websocket\events\DisconnectEvent;
use goodizer\websocket\events\MessageEvent;
use goodizer\websocket\events\ErrorEvent;

/**
 * Interface CommandsInterface
 * @package goodizer\websocket\commands
 */
interface CommandsInterface
{
    /**
     * @param ConnectEvent $event
     * @return void
     */
    public function onConnect(ConnectEvent $event);

    /**
     * @param DisconnectEvent $event
     * @return void
     */
    public function onDisconnect(DisconnectEvent $event);

    /**
     * @param MessageEvent $event
     * @return mixed
     */
    public function onMessage(MessageEvent $event);

    /**
     * @param ErrorEvent $event
     * @return mixed
     */
    public function onError(ErrorEvent $event);
}
