<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use goldencode\Bitrix\Restify\Executors\IblockElementRest;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyIblockComponent extends RouterComponent {
	public function __construct() {
		$executor = new IblockElementRest([
			'iblockId' => 1,
		]);
		parent::__construct($executor);
	}
}
