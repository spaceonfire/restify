<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use goldencode\Bitrix\Restify\Executors\IblockElementRest;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyIblockElementComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new IblockElementRest($this->arParams);
		$this->setExecutor($executor);
		parent::executeComponent();
	}
}
