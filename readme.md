# Restify

Модуль, который позволяет с легкостью создавать REST API для 1С-Битрикс

## Начало работы

### Необходимые условия окружения

Для запуска проекта в системе должны быть установлены:

- PHP >= 7
- Composer

### Установка

```bash
composer require spaceonfire/restify
```

## Examples

### Events

API output transformation

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Emonkak\HttpException\InternalServerErrorHttpException;

EventManager::getInstance()->addEventHandler(
	'spaceonfire.restify',
	'transform',
	'modifyStatusCode'
);

function throw500() {
	throw new InternalServerErrorHttpException();
}

function modifyStatusCode(Event $event) {
	$params = $event->getParameters();
	$params['statusCode'] = 500;
}
```

Build full path to image in FileFormatter

```php
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;

EventManager::getInstance()->addEventHandler('spaceonfire.restify', 'OnFileFormatter', function(Event $event) {
	$params = $event->getParameters();
	$params['data']['SRC'] = $_SERVER['REQUEST_SCHEME'] . '://' . (env('DOMAIN') ?: $_SERVER['HTTP_HOST']) . $params['data']['SRC'];
});
```
