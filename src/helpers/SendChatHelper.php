<?php

namespace goodizer\websocket\helpers;

use Yii;
use yii\helpers\Json;
use goodizer\websocket\Client;

class SendChatHelper
{
  /**
   * @return Client
   */
  public static function getClient()
  {
    $host = Yii::$app->params['web_socket_server']['host'] ?? 'localhost';
    $port = Yii::$app->params['web_socket_server']['port'] ?? 8000;
    $address = Yii::$app->params['web_socket_server']['address'] ?? '0.0.0.0';
    $protocol = (Yii::$app->params['web_socket_server']['protocol'] ?? 'wss');

    return new Client("{$protocol}://{$host}:{$port}/chat", [
      //'dns' => $address,
    ]);
  }

  /**
   * Send data to websocket to notify users
   *
   * @param int $commentId
   * @return mixed
   * @throws \Exception
   */
  public static function sendCommentCreated($commentId)
  {
    $data = [
      'method' => 'commentCreated',
      'params' => [
        'commentId' => $commentId,
      ],
    ];

    return static::request($data);
  }

  /**
   * Send data to websocket to notify users
   *
   * @param int $commentId
   * @param int $eventId
   * @return mixed
   * @throws \Exception
   */
  public static function sendCommentDeleted($commentId, $eventId)
  {
    $data = [
      'method' => 'commentDeleted',
      'params' => [
        'eventId' => $eventId,
        'commentId' => $commentId,
      ],
    ];

    return static::request($data);
  }

  /**
   * Send data to websocket to notify users
   *
   * @param int $commentId
   * @param int $like_type
   * @return mixed
   * @throws \Exception
   */
  public static function sendCommentVoted($commentId, $like_type = 1)
  {
    $data = [
      'method' => 'commentVoted',
      'params' => [
        'commentId' => $commentId,
        'likeType' => $like_type,
      ],
    ];

    return static::request($data);
  }

  /**
   * Send data to websocket to notify users
   *
   * @param int $speakerId
   * @return mixed
   * @throws \Exception
   */
  public static function sendUserSpeakerAssigned($speakerId)
  {
    $data = [
      'method' => 'userSpeakerAssigned',
      'params' => [
        'speakerId' => $speakerId,
      ],
    ];

    return static::request($data);
  }

  /**
   * Send data to websocket to notify users
   *
   * @param int $eventId
   * @param int $userId
   * @return mixed
   * @throws \Exception
   */
  public static function sendUserSpeakerRevoked($eventId, $userId)
  {
    $data = [
      'method' => 'userSpeakerRevoked',
      'params' => [
        'eventId' => $eventId,
        'userId' => $userId,
      ],
    ];

    return static::request($data);
  }

  /**
   * Get users online on websocket
   *
   * @param int $eventId
   * @return mixed
   * @throws \Exception
   */
  public static function sendGetUsersOnline($eventId)
  {
    $data = [
      'method' => 'getUsersOnline',
      'params' => [
        'eventId' => $eventId,
      ],
    ];
    $result = static::request($data, true);
    return isset($result['error']) ? ['broadcast_statistics' => $result] : $result;
  }

  /**
   * @param array $data
   * @param bool $await
   * @return array|mixed|null
   */
  public static function request($data, $await = false)
  {
    try {
      $client = static::getClient();

      if ($await) {
        return $client->receive($data);
      } else {
        return $client->release($data);
      }
    } catch (\Throwable $e) {
      Yii::error("Notification not send to websocket.\r\n" . $e->getMessage() . "\r\n" . $e->getTraceAsString());
      return [
        'error' => $e->getMessage()
      ];
    }
  }
}
