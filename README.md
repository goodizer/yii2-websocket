yii2-helpers
=================

[![Latest Stable Version](https://img.shields.io/packagist/v/goodizer/yii2-websocket.svg)](https://packagist.org/packages/goodizer/yii2-websocket)
[![License](https://poser.pugx.org/goodizer/yii2-websocket/license)](https://packagist.org/packages/goodizer/yii2-websocket)
[![Total Downloads](https://poser.pugx.org/goodizer/yii2-websocket/downloads)](https://packagist.org/packages/goodizer/yii2-websocket)
[![Monthly Downloads](https://poser.pugx.org/goodizer/yii2-websocket/d/monthly)](https://packagist.org/packages/goodizer/yii2-websocket)
[![Daily Downloads](https://poser.pugx.org/goodizer/yii2-websocket/d/daily)](https://packagist.org/packages/goodizer/yii2-websocket)

Web-socket component based on workerman/workerman for Yii2

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

> Note: Check the [composer.json](https://github.com/goodizer/yii2-websocket/blob/master/composer.json) for this extension's requirements and dependencies. 

Either run

```
$ php composer.phar require goodizer/yii2-websocket
```

or add

```
"goodizer/yii2-websocket": "*"
```

to the ```require``` section of your `composer.json` file.

## Usage

### GridSearchHelper

Set websocket component config.

```php
    'components' => [
        ...
        'websocketServer' => [
          'class' => 'goodizer\websocket\Server',
          'commandClass' => 'console\extensions\Commands',//Your class that inherit goodizer\websocket\Commands
          'host' => 'localhost',
          'port' => 8000,
          'isSecure' => true,
          'localCert' => null,
          'localPk' => null,
        ],
        ...
    ],
```
Create controller

```php
<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use goodizer\websocket\Server;

/**
 * Class WebsocketServerController
 * @package console\controllers
 */
class WebsocketServerController extends Controller
{
  /**
   * @throws \Exception
   */
  public function actionStart()
  {
    /** @var Server $server */
    $server = Yii::$app->webSocketServer;

    $server->run();
  }
}
```

... and run in the console:

\#php yii websocket-server\start