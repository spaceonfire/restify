<?php

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Flight;

if (!Loader::includeModule('goldencode.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifySwaggerComponent extends \CBitrixComponent {
	public function buildSpec() {
		$restifyDefinitionsStr = file_get_contents(__DIR__ . '/definitions-v2.json');
		$restifyDefinitions = json_decode($restifyDefinitionsStr, true);

		// Prevent duplicating swagger field value by recursive merge
		unset($this->arParams['spec']['swagger']);

		$openapi = array_merge_recursive(
			$restifyDefinitions,
			$this->arParams['spec']
		);

		Flight::json($openapi);
	}

	public function executeComponent() {
		Flight::route('/swagger.json', [$this, 'buildSpec']);
		Flight::route('/', [$this, 'includeComponentTemplate']);
		Flight::start();
	}
}
