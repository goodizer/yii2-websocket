<?php

namespace goodizer\websocket;

use yii\base\Component;
use yii\base\Exception;
use yii\helpers\Json;

/**
 * Class Client
 * @package goodizer\websocket
 */
class Client extends Component
{
    /**
     * @var string
     */
    public $host = 'localhost';

    /**
     * @var int
     */
    public $port = 8000;

    /**
     * @var bool
     */
    public $isSecure = true;

    /**
     * @var string
     */
    public $localCert;

    /**
     * @var string
     */
    public $localPk;

    /**
     * @var int
     */
    public $timeout = 5;

    /**
     * @var int
     */
    public $fragmentSize = 4096;

    /**
     * @var resource
     */
    protected $_socket;

    /**
     * @var bool
     */
    protected $_isConnected = false;

    public function __destruct()
    {
        if ($this->_socket) {
            if (get_resource_type($this->_socket) === 'stream') {
                fclose($this->_socket);
            }

            $this->_socket = null;
        }
    }

    /**
     * Init properties, Yii events and Worker events
     */
    public function init()
    {
        parent::init();
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function send($data)
    {
        $payload = Json::encode($data);

        if (!$this->_isConnected) {
            $this->connect();
        }

        $masked = true;
        $payloadLength = strlen($payload);
        $fragmentCursor = 0;

        while ($payloadLength > $fragmentCursor) {
            $sub_payload = substr($payload, $fragmentCursor, $this->fragmentSize);
            $fragmentCursor += $this->fragmentSize;
            $final = $payloadLength <= $fragmentCursor;

            $this->sendFragment($final, $sub_payload, $masked);
        }
    }

    /**
     * @throws Exception
     */
    protected function connect()
    {
        $contextOptions = [];
        $transport = 'tcp';

        if ($this->isSecure && $this->localCert && $this->localPk) {
            $transport = 'ssl';
            $contextOptions = [
                'ssl' => [
                    'local_cert' => $this->localCert,
                    'local_pk' => $this->localPk,
                    'verify_peer' => false,
                ]
            ];
        }

        $socketUri = "{$this->host}:{$this->port}";

        if (!empty($contextOptions)) {
            $this->_socket = \stream_socket_client(
                "{$transport}://{$socketUri}",
                $errno,
                $errstr,
                $this->timeout,
                \STREAM_CLIENT_ASYNC_CONNECT,
                \stream_context_create($contextOptions)
            );
        } else {
            $this->_socket = \stream_socket_client(
                "{$transport}://{$socketUri}",
                $errno,
                $errstr,
                $this->timeout,
                \STREAM_CLIENT_ASYNC_CONNECT
            );
        }

        if (!$this->_socket || !\is_resource($this->_socket)) {
            throw new Exception($errstr);
        }

        stream_set_timeout($this->_socket, $this->timeout);

        // Generate the WebSocket key.
        $key = static::generateKey();

        $headers = array(
            "GET {$socketUri} HTTP/1.1",
            "Host: {$socketUri}",
            'Connection: Upgrade',
            'Upgrade: websocket',
            "Sec-Websocket-Key: {$key}",
            'Sec-Websocket-Version: 13',
        );

        $headersBuffer = implode("\r\n", $headers) . "\r\n\r\n";

        // Send headers.
        $this->write($headersBuffer);

        // Get server response header (terminated with double CR+LF).
        $response = stream_get_line($this->_socket, 1024, "\r\n\r\n");

        // Validate response.
        if (!preg_match('#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches)) {
            throw new Exception("Connection to '{$transport}://{$socketUri}' failed: Server sent invalid upgrade response:\n{$response}");
        }

        $keyAccept = trim($matches[1]);
        $expectedResponse = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        if ($keyAccept !== $expectedResponse) {
            throw new Exception('Server sent bad upgrade response.');
        }

        $this->_isConnected = true;
    }

    /**
     * @param $final
     * @param $payload
     * @param $masked
     * @throws Exception
     */
    protected function sendFragment($final, $payload, $masked)
    {
        $binStr = '';
        $binStr .= (bool)$final ? '1' : '0';
        $binStr .= '000';
        $binStr .= sprintf('%04b', 1);
        $binStr .= $masked ? '1' : '0';
        $payloadLength = strlen($payload);
        if ($payloadLength > 65535) {
            $binStr .= decbin(127);
            $binStr .= sprintf('%064b', $payloadLength);
        } elseif ($payloadLength > 125) {
            $binStr .= decbin(126);
            $binStr .= sprintf('%016b', $payloadLength);
        } else {
            $binStr .= sprintf('%07b', $payloadLength);
        }

        $frame = '';
        $mask = '';

        // Write frame head to frame.
        foreach (str_split($binStr, 8) as $str) {
            $frame .= chr(bindec($str));
        }

        // Handle masking
        if ($masked) {
            // generate a random mask:
            for ($i = 0; $i < 4; $i++) {
                $mask .= chr(rand(0, 255));
            }

            $frame .= $mask;
        }

        // Append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        $this->write($frame);
    }

    /**
     * @param $data
     * @throws Exception
     */
    protected function write($data)
    {
        $written = fwrite($this->_socket, $data);

        if ($written < strlen($data)) {
            throw new Exception(
                "Could only write $written out of " . strlen($data) . " bytes."
            );
        }
    }

    /**
     * Generate a random string for WebSocket key.
     * @return string Random string
     */
    protected static function generateKey()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
        $charsLength = strlen($chars);
        $key = '';

        for ($i = 0; $i < 16; $i++) $key .= $chars[mt_rand(0, $charsLength - 1)];

        return base64_encode($key);
    }
}
