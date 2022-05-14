<?php

namespace goodizer\websocket\helpers;

use Yii;
use yii\helpers\Json;
use goodizer\websocket\Client;
use app\models\yii2\Meetings_yii2;

class SendNotificationHelper
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

    return new Client("{$protocol}://{$host}:{$port}/notice", [
      //'dns' => $address,
    ]);
  }

  const MEETING_EVENT_INVITED = 1;
  const MEETING_EVENT_APPROVED = 2;
  const MEETING_EVENT_REJECTED = 3;
  const MEETING_EVENT_EDITED = 4;

  /**
   * Send data to websocket to notify user
   *
   * @param Meetings_yii2 $meeting
   * @param int $type
   * @param int|null $userId
   * @return array|mixed|null
   * @throws \Exception
   */
  public static function sendByMeeting($meeting, $type, $userId = null)
  {
    if (!$type) {
      $type = static::MEETING_EVENT_INVITED;
    }

    if (!$userId) {
      $userId = $meeting->from_user_id;
    }

    $data = [
      'method' => 'meetingNotify',
      'params' => [
        'userId' => $userId,
        'meetingId' => $meeting->id,
        'type' => $type,
      ],
    ];

    try {
      $client = static::getClient();
      return $client->release($data);
    } catch (\Exception $e) {
      Yii::error("Notification not send to websocket.\r\n" . $e->getMessage() . "\r\n" . $e->getTraceAsString());
      return [
        'error' => $e->getMessage()
      ];
    }
  }
}
