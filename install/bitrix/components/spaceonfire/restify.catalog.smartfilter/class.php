<?php

namespace spaceonfire\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use spaceonfire\Restify\Executors\CatalogSmartFilterRest;

if (!Loader::includeModule('spaceonfire.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyCatalogSmartFilterComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new CatalogSmartFilterRest($this->arParams);
		$this->setExecutor($executor);
		$this->cors();
		$this->route('GET /', [$this, 'readMany']);
		$this->start();
	}
}
