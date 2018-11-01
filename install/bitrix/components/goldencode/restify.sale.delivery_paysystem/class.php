<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use goldencode\Bitrix\Restify\Executors\SaleDeliveryPaySystemRest;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifySaleDeliveryPaySystemComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new SaleDeliveryPaySystemRest($this->arParams);
		$this->setExecutor($executor);
		$this->route('GET /', [$this, 'readMany']);
		$this->start();
	}
}
