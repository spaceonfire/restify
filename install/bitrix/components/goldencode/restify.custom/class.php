<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\DB\Exception;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyCustomComponent extends RouterComponent {
	public function executeComponent() {
		if (!$this->arParams['executor']) {
			throw new Exception(Loc::getMessage('REQUIRED_PROPERTY', [
				'#PROPERTY#' => 'executor',
			]));
		}

		$this->setExecutor($this->arParams['executor']);
		if ($this->arParams['enableCORS']) {
			$this->cors();
		}
		foreach ($this->arParams['routes'] as $pattern => $method) {
			$this->route($pattern, [$this, $method]);
		}
		$this->start();
	}
}
