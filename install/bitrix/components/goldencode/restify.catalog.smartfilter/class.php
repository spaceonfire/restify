<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use goldencode\Bitrix\Restify\Executors\CatalogSmartFilterRest;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyCatalogSmartFilterComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new CatalogSmartFilterRest($this->arParams);
		$this->setExecutor($executor);
		$this->route('GET /', [$this, 'readMany']);
		$this->start();
	}
}
