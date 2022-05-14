<?php

namespace goodizer\websocket\helpers;


class StatHelper
{
  /**
   * @param bool $real
   * @return string
   */
  public static function getLoads($real = true)
  {

    $size = \memory_get_usage($real);
    $pSize = \memory_get_peak_usage($real);
    $cpu = null;
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
      $cpu = @\sys_getloadavg()[0];
      $cpu = ltrim(str_replace('.', '', $cpu), '0') . '%';
    }

    return sprintf('CPU: %s; Memory(curr/peak): %s', $cpu, self::rnd($size) . ' | ' . self::rnd($pSize));
  }

  /**
   * @param $s
   * @return string
   */
  public static function rnd($s)
  {
    $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
    return @\round($s / pow(1024, ($i = floor(log($s, 1024)))), 2) . $unit[$i];
  }
}
