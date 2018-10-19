<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use goldencode\Bitrix\Restify\Executors\IblockSectionRest;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyIblockSectionComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new IblockSectionRest($this->arParams);
		$this->setExecutor($executor);
		parent::executeComponent();
	}
}
