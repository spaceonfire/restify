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
