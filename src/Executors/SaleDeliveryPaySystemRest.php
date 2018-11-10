<?php

namespace spaceonfire\Restify\Executors;

use Emonkak\HttpException\InternalServerErrorHttpException;
use Exception;

class SaleDeliveryPaySystemRest implements IExecutor {
	use RestTrait;

	private $entity = 'Bitrix\Sale\Internals\DeliveryPaySystemTable';

	/**
	 * SaleDeliveryPaySystemRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws InternalServerErrorHttpException
	 * @throws Exception
	 */
	public function __construct($options) {
		$this->loadModules([
			'sale',
		]);
		$this->filter = [];
		$this->order = [
			'DELIVERY_ID' => 'ASC',
		];
		$this->checkEntity();
		$this->setSelectFieldsFromEntityClass();
		$this->setPropertiesFromArray($options);
		$this->registerBasicTransformHandler();
		$this->buildSchema();
	}

	public function readMany() {
		return $this->readORM();
	}
}
