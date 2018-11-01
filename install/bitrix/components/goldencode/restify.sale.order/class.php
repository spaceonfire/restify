<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use goldencode\Bitrix\Restify\Executors\SaleOrderRest;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifySaleOrderComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new SaleOrderRest($this->arParams);
		$this->setExecutor($executor);
		$this->route('GET /', [$this, 'readMany']);
		$this->route('POST /', [$this, 'create']);
		$this->route('GET /@id:[0-9]+', [$this, 'readOne']);
		$this->start();
	}
}
