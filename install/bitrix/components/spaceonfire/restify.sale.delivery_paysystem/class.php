<?php

namespace spaceonfire\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use spaceonfire\Restify\Executors\SaleDeliveryPaySystemRest;

if (!Loader::includeModule('spaceonfire.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifySaleDeliveryPaySystemComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new SaleDeliveryPaySystemRest($this->arParams);
		$this->setExecutor($executor);
		$this->cors();
		$this->route('GET /', [$this, 'readMany']);
		$this->start();
	}
}
