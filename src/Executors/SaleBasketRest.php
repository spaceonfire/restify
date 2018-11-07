<?php

namespace goldencode\Bitrix\Restify\Executors;

use Bitrix\Main\Localization\Loc;
use CSaleBasket;
use Emonkak\HttpException\BadRequestHttpException;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Emonkak\HttpException\NotFoundHttpException;
use Exception;

class SaleBasketRest implements IExecutor {
	use RestTrait;

	private $entity = 'Bitrix\Sale\Internals\BasketTable';

	/**
	 * SaleBasketRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws InternalServerErrorHttpException
	 * @throws Exception
	 */
	public function __construct($options) {
		$this->loadModules([
			'sale',
			'catalog',
		]);

		$this->filter = [];

		$this->checkEntity();
		$this->setSelectFieldsFromEntityClass();
		$this->setPropertiesFromArray($options);

		$sep = $this->ormNestedSelectSeparator;
		$this->select = [
			'*',
			'PRODUCT' . $sep => 'PRODUCT',
			'ELEMENT' . $sep => 'PRODUCT.IBLOCK',
		];

		$this->registerBasicTransformHandler();
		$this->buildSchema();
	}

	public function read() {
		$this->filter = array_merge($this->filter, [
			'FUSER_ID' => (int) CSaleBasket::GetBasketUserID(true),
			'LID' => SITE_ID,
			'ORDER_ID' => 'NULL',
		]);
		return $this->readORM();
	}

	public function update($id = null) {
		$this->registerOneItemTransformHandler();

		if (!$id) {
			$id = $this->body['ID'];
			unset($this->body['ID']);
		}

		if (!$id) {
			throw new NotFoundHttpException();
		}

		$quantity = (int) $this->body['QUANTITY'] || 1;

		// Find basket item by product id
		$action = 'add';
		$this->filter = ['PRODUCT_ID' => $id];
		$this->select = ['ID'];
		$item = array_pop($this->read());
		if ($item) {
			$action = 'update';
		}

		switch ($action) {
			case 'update':
				$result = (new CSaleBasket())->Update($item['ID'], ['QUANTITY' => $quantity]);
				$successMessage = Loc::getMessage('SALE_BASKET_UPDATE');
				break;

			case 'add':
			default:
				$result = \Add2BasketByProductID($id, $quantity);
				$successMessage = Loc::getMessage('SALE_BASKET_ADD');
				break;
		}

		if (!$result) {
			global $APPLICATION;
			throw new BadRequestHttpException($APPLICATION->LAST_ERROR);
		}

		return [
			$this->success($successMessage),
		];
	}

	public function delete($id) {
		$this->registerOneItemTransformHandler();

		$this->filter = ['PRODUCT_ID' => $id];
		$this->select = ['ID'];
		$item = array_pop($this->read());

		$result = true;
		if (isset($item)) {
			$result = (new CSaleBasket())->Delete($item['ID']);
		}

		if (!$result) {
			global $APPLICATION;
			throw new InternalServerErrorHttpException($APPLICATION->LAST_ERROR);
		}

		return [
			$this->success(Loc::getMessage('SALE_BASKET_DELETE')),
		];
	}
}
