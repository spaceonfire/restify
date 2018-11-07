<?php

namespace goldencode\Bitrix\Restify\Executors;

use Bitrix\Main\Localization\Loc;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Exception;

class CatalogSmartFilterRest implements IExecutor {
	use RestTrait;

	/**
	 * CatalogSmartFilterRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws InternalServerErrorHttpException
	 * @throws Exception
	 */
	public function __construct($options) {
		$this->loadModules(['iblock']);

		if (!$options['IBLOCK_ID']) {
			throw new InternalServerErrorHttpException(Loc::getMessage('REQUIRED_PROPERTY', [
				'#PROPERTY#' => 'iblockId',
			]));
		}
	}

	public function readMany() {
		global $APPLICATION;
		// Force ajax mode
		$_REQUEST['ajax'] = 'y';
		$APPLICATION->IncludeComponent(
			'bitrix:catalog.smart.filter',
			'restify',
			$this->component->arParams,
			$this->component
		);
	}
}
