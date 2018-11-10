<?php

namespace spaceonfire\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use spaceonfire\Restify\Executors\SalePaySystemRest;

if (!Loader::includeModule('spaceonfire.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifySalePaySystemComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new SalePaySystemRest($this->arParams);
		$this->setExecutor($executor);
		$this->cors();
		$this->route('GET /', [$this, 'readMany']);
		$this->route('GET /@id', [$this, 'readOne']);
		$this->start();
	}
}
