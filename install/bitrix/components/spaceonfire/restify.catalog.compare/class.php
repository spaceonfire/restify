<?php

namespace spaceonfire\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use spaceonfire\Restify\Executors\CatalogCompareRest;

if (!Loader::includeModule('spaceonfire.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyCatalogCompareComponent extends RouterComponent {
	public function executeComponent() {
//		$this->setJsonOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT | JSON_FORCE_OBJECT);
		$executor = new CatalogCompareRest($this->arParams);
		$this->setExecutor($executor);
		$this->cors();
		$this->route('GET /', [$this, 'read']);
		$this->route('GET /lists', [$this, 'getCompareLists']);
		$this->route('POST /@id:[0-9]+', [$this, 'add']);
		$this->route('DELETE /@id:[0-9]+', [$this, 'delete']);
		$this->route('DELETE *', [$this, 'delete']);
		$this->start();
	}
}
