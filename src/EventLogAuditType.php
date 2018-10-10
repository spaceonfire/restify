<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/../install/index.php');

class EventLogAuditType {
	public static function registerAuditTypes() {
		return [
			'RESTIFY' => 'RESTIFY: ' . Loc::getMessage('RESTIFY_MODULE_NAME'),
		];
	}
}
