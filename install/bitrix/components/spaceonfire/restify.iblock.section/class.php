<?php

namespace spaceonfire\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use spaceonfire\Restify\Executors\IblockSectionRest;

if (!Loader::includeModule('spaceonfire.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyIblockSectionComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new IblockSectionRest($this->arParams);
		$this->setExecutor($executor);
		parent::executeComponent();
	}
}
