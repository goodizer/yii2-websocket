<?php

namespace goodizer\websocket\commands;

use goodizer\websocket\events\IntervalEvent;

/**
 * Interface IntervalInterface
 * @package goodizer\websocket\commands
 */
interface IntervalInterface
{
  /**
   * @param IntervalEvent $event
   * @return mixed
   */
    public function onInterval(IntervalEvent $event);
}
