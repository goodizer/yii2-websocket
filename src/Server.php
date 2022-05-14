<?php

namespace goodizer\websocket;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\console\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\log\FileTarget;
use goodizer\websocket\helpers\StatHelper;
use goodizer\websocket\extensions\ServerWrapper;

/**
 * Class Server
 * @package goodizer\websocket
 */
class Server extends Component
{
  const LOG_TRACE_MAX_LEVEL = 5;

  /**
   * @var string
   */
  public $host = 'localhost';

  /**
   * @var string
   */
  public $address = '0.0.0.0';

  /**
   * @var int
   */
  public $port = 8000;

  /**
   * @var string
   */
  public $protocol = 'ws';

  /**
   * Class names of commands handlers
   * @var string[]
   */
  public $commands;

  /**
   * @var string local cert
   */
  public $localCert;

  /**
   * @var string local primary key
   */
  public $localPk;

  /**
   * @var string pass for cert
   */
  public $certPassphrase;

  /**
   * @var ServerWrapper
   */
  private $_server;

  /**
   * @inheritDoc
   */
  public function init()
  {
    parent::init();

    if (empty($this->commands) || !is_array($this->commands)) {
      throw new InvalidConfigException("Property 'commands' must be an arrays configs");
    }

    if (!$this->address || !$this->host || !$this->port) {
      throw new InvalidConfigException("Properties 'address', 'host' and 'port' are required");
    }

    /** Disable file log, cause socket server will down */
      foreach (Yii::$app->getLog()->targets as $k => $target) {
      if ($k == 'file' || $target instanceof FileTarget) {
        $target->enabled = false;
      }
    }

    defined('WEB_SOCKET_ENABLE_LOG') or define('WEB_SOCKET_ENABLE_LOG', false);
    defined('WEB_SOCKET_DISABLE_DEV_MODE') or define('WEB_SOCKET_DISABLE_DEV_MODE', false);
    putenv('RATCHET_DISABLE_XDEBUG_WARN=true');

    $sslContext = [];

    if ($this->localCert && $this->localPk) {
      if (!file_exists($this->localCert)) {
        throw new InvalidConfigException("Certificate file does not exist. '{$this->localCert}'");
      }

      if (!file_exists($this->localPk)) {
        throw new InvalidConfigException("Privet key file does not exist. '{$this->localPk}'");
      }

      $sslContext = [
        'local_cert' => $this->localCert,
        'local_pk' => $this->localPk,
        'passphrase' => (string)$this->certPassphrase,
        'allow_self_signed' => !WEB_SOCKET_DISABLE_DEV_MODE, // Allow self signed certs (should be false in production)
        'verify_peer' => false,
        //'verify_peer_name' => false,
      ];

      if (WEB_SOCKET_ENABLE_LOG) {
        echo Console::ansiFormat("Context for stream_socket:\n", [Console::FG_PURPLE, Console::BOLD]);
        echo Console::ansiFormat(var_export($sslContext, true). "\n", [Console::ENCIRCLED]);
      }
    }

    $this->_server = new ServerWrapper($this->host, $this->port, $this->address, $sslContext);

    foreach ($this->commands as $name => $config) {
      if (!$class = ArrayHelper::remove($config, 'class')) {
        throw new InvalidConfigException("Property 'commands' must contain class names");
      }
      if (!$path = ArrayHelper::remove($config, 'path')) {
        throw new InvalidConfigException("Property 'path' must contain class names");
      }

      $config['id'] = $name;
      $config['enableLog'] = WEB_SOCKET_ENABLE_LOG;

      $this->_server->route($path, new App($class, $config), ['*']);
    }
  }

  /**
   * @var int
   */
  private $_retryCounter = 0;

  /**
   * @throws Exception
   */
  public function run()
  {
    try {
      $this->_retryCounter++;
      $this->_server->run();
    } catch (\Throwable $e) {
      echo Console::ansiFormat("\nServer error:\n", [Console::FG_RED, Console::BOLD]);
      echo Console::ansiFormat("\n{$e->getMessage()}\n#0 {$e->getFile()}({$e->getLine()})\n", [Console::FG_RED, Console::BOLD]);
      $this->_logFile($e);

      if ($this->_retryCounter >= 100) {
        echo Console::ansiFormat("\nretry iterations are limited: max 100 retries;\n", [Console::FG_RED, Console::BOLD]);
        throw new \yii\console\Exception('Retry iterations are limited: max 100 retries', 500, $e);
      }

      echo Console::ansiFormat("\nRETRY [{$this->_retryCounter}]\n", [Console::FG_GREEN, Console::BOLD]);

      $this->stop();
      $this->run();
    }
  }

  /**
   * @throws Exception
   */
  public function stop()
  {
      $this->_server->stop();
  }

  /**
   * @param \Throwable $e
   */
  private function _logFile(\Throwable $e)
  {
    $traces = [];
    $count = 0;
    $rows = explode(PHP_EOL, $e->getTraceAsString());

    foreach ($rows as $trace) {
      $traces[] = $trace;
      if (++$count >= static::LOG_TRACE_MAX_LEVEL) {
        $traces[] = '...hidden ' . (sizeof($rows) - $count) . ' lines';
        break;
      }
    }

    unset($rows);

    $file = date('Y_m_d');
    $path = Yii::getAlias("@app/runtime/websocket/{$file}.log");

    if (!is_dir(dirname($path))) {
      mkdir(dirname($path), 0777, true);
    }

    $usage = StatHelper::getLoads();
    file_put_contents($path, implode(PHP_EOL, [
        date("Y-m-d H:i:s") . " [" . get_class($e) . "] [{$e->getCode()}] [{$usage}]",
        $e->getMessage(),
        implode(PHP_EOL, $traces),
      ]) . PHP_EOL . PHP_EOL, FILE_APPEND);
  }
}
