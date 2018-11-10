<?php

namespace spaceonfire\Restify\Executors;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use CCatalog;
use CIBlockElement;
use CPrice;
use Emonkak\HttpException\BadRequestHttpException;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Emonkak\HttpException\NotFoundHttpException;
use Exception;

class CatalogCompareRest implements IExecutor {
	use RestTrait {
		prepareQuery as private _prepareQuery;
	}

	public $name = null;

	protected $iblockId;
	protected $prices = [];
	protected $properties = [];

	private $entity = 'Bitrix\Iblock\ElementTable';
	private $catalog = null;

	/**
	 * CatalogCompareRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws InternalServerErrorHttpException
	 * @throws Exception
	 */
	public function __construct($options) {
		$this->loadModules(['iblock']);

		$required = [
			'name',
			'iblockId',
		];
		foreach ($required as $field) {
			if (!$options[$field]) {
				throw new InternalServerErrorHttpException(Loc::getMessage('REQUIRED_PROPERTY', [
					'#PROPERTY#' => $field,
				]));
			}
		}

		$this->setPropertiesFromArray($options);

		// Support catalog
		if ($this->loadModules('catalog', false)) {
			$this->catalog = (bool) CCatalog::GetByID($this->iblockId);
		}

		$this->buildSchema();
	}

	private function getSessionKey() {
		global $SPACEONFIRE_RESTIFY;
		return $SPACEONFIRE_RESTIFY->getId() . '.catalog.compare';
	}

	public function prepareQuery() {
		$this->_prepareQuery();

		// Delete iblock props from filter
		unset($this->filter['IBLOCK_ID']);
		unset($this->filter['IBLOCK_CODE']);
		unset($this->filter['IBLOCK_SITE_ID']);
		unset($this->filter['IBLOCK_TYPE']);

		// Set IBLOCK_ID filter
		$this->filter['IBLOCK_ID'] = $this->iblockId;

		// Force check permissions
		$this->filter['CHECK_PERMISSIONS'] = 'Y';

		// Rewrite select fields
		$this->select = [
			'ID',
			'IBLOCK_ID',
			'IBLOCK_SECTION_ID',
			'NAME',
			'DETAIL_PAGE_URL',
			'IBLOCK_SECTION_ID',
		];
	}

	public function read() {
		$this->registerBasicTransformHandler();
		if ($this->catalog) {
			$this->registerCatalogTransform();
		}
		$this->registerIblockTransform();

		$compareList = $this->name;

		if (empty($_SESSION[$this->getSessionKey()])) {
			$_SESSION[$this->getSessionKey()] = [];
		}

		$items = array_filter($_SESSION[$this->getSessionKey()], function ($item) use ($compareList) {
			return $item['COMPARE_LIST'] === $compareList;
		});

		$items = array_values($items);

		return $items;
	}

	public function add($id) {
		$this->registerOneItemTransformHandler();

		$element = CIBlockElement::GetList(
			[],
			array_merge($this->filter, [
				'ID' => $id,
			]),
			false,
			$this->navParams,
			$this->select
		)->GetNext(true, false);

		if (!$element) {
			throw new NotFoundHttpException();
		}

		$element['COMPARE_LIST'] = $this->name;
		$element['SECTIONS'] = [];

		$sectionsQ = CIBlockElement::GetElementGroups($id, true, [
			'ID',
			'IBLOCK_SECTION_ID',
			'NAME',
			'CODE',
			'LEFT_MARGIN',
			'RIGHT_MARGIN',
			'DEPTH_LEVEL',
		]);
		while ($section = $sectionsQ->GetNext(true, false)) {
			$element['SECTIONS'][] = $section;
		}

		$_SESSION[$this->getSessionKey()][] = $element;

		return [
			$this->success(Loc::getMessage('CATALOG_COMPARE_ADD_SUCCESS')),
		];
	}

	public function delete($id = null) {
		$this->registerOneItemTransformHandler();

		if ($id) {
			foreach ($_SESSION[$this->getSessionKey()] as $i => $item) {
				if ($item['ID'] === $id && $item['COMPARE_LIST'] === $this->name) {
					unset($_SESSION[$this->getSessionKey()][$i]);
				}
			}
		} else {
			foreach ($_SESSION[$this->getSessionKey()] as $i => $item) {
				if ($item['COMPARE_LIST'] === $this->name) {
					unset($_SESSION[$this->getSessionKey()][$i]);
				}
			}
		}

		return [
			$this->success(
				Loc::getMessage(
					$id ?
						'CATALOG_COMPARE_DELETE_SUCCESS' :
						'CATALOG_COMPARE_CLEAR_SUCCESS'
				)
			),
		];
	}

	public function getCompareLists() {
		$this->registerOneItemTransformHandler();

		return [
			array_unique(
				array_map(function ($item) { return $item['COMPARE_LIST']; }, $_SESSION[$this->getSessionKey()])
			),
		];
	}

	private function registerCatalogTransform() {
		global $SPACEONFIRE_RESTIFY;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$SPACEONFIRE_RESTIFY->getId(),
			'transform',
			[$this, 'catalogTransform']
		);
	}

	public function catalogTransform(Event $event) {
		$params = $event->getParameters();
		foreach ($params['result'] as $key => $item) {
			$item['BASE_PRICE'] = CPrice::GetBasePrice($item['ID']);
			$item['CAN_BUY'] =
				$item['BASE_PRICE']['PRICE'] &&
				(
					$item['BASE_PRICE']['PRODUCT_CAN_BUY_ZERO'] === 'Y' ||
					$item['BASE_PRICE']['PRODUCT_NEGATIVE_AMOUNT_TRACE'] === 'Y' ||
					(int) $item['BASE_PRICE']['PRODUCT_QUANTITY'] > 0
				);

			$prices = array_filter($this->prices, function ($p) use ($item) {
				return $p !== $item['BASE_PRICE']['CATALOG_GROUP_NAME'];
			});

			if (!empty($prices)) {
				$pricesValues = [];
				foreach ($prices as $price) {
					$pricesValues[$price] = CPrice::GetList([], [
						'PRODUCT_ID' => $item['ID'],
						'CATALOG_GROUP_ID' => $pricesValues,
					])->Fetch();
				}
				$item['PRICES'] = $pricesValues;
			}

			if (!$item['BASE_PRICE'] && !$item['PRICE']) {
				unset($item['BASE_PRICE']);
				unset($item['PRICE']);
				unset($item['CAN_BUY']);
			}

			$params['result'][$key] = $item;
		}
	}

	private function registerIblockTransform() {
		global $SPACEONFIRE_RESTIFY;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$SPACEONFIRE_RESTIFY->getId(),
			'transform',
			[$this, 'iblockTransform'],
			false,
			99999
		);
	}

	public function iblockTransform(Event $event) {
		$params = $event->getParameters();

		if (empty($params['result'])) {
			throw new BadRequestHttpException(Loc::getMessage('CATALOG_COMPARE_EMPTY', [
				'#COMPARE_NAME#' => $this->name,
			]));
		}

		// Properties
		$propsFilter = [
			'ACTIVE' => 'Y',
			'IBLOCK_ID' => $this->iblockId,
		];
		if (!empty($this->properties)) {
			$propsFilter['CODE'] = $this->properties;
		}

		$properties = PropertyTable::getList([
			'select' => [
				'ID',
				'NAME',
				'CODE',
				'PROPERTY_TYPE',
				'MULTIPLE',
				'LINK_IBLOCK_ID',
				'USER_TYPE',
				'USER_TYPE_SETTINGS',
			],
			'filter' => $propsFilter,
			'order' => ['SORT' => 'ASC']
		])->fetchAll();

		$propertySelect = array_map(function ($prop) { return 'PROPERTY_' . $prop['ID']; }, $properties);
		$propertySelect[] = 'ID';

		$propertyQ = CIBlockElement::GetList(
			[],
			[
				'ID' => array_map(function ($item) { return $item['ID']; }, $params['result']),
				'IBLOCK_ID' => $this->iblockId,
			],
			false,
			false,
			$propertySelect
		);

		$propVals = [];
		while ($propVal = $propertyQ->Fetch()) {
			unset($propVal['IBLOCK_ELEMENT_ID']);
			$propVals[$propVal['ID']] = $propVal;
		}

		$params['result'] = [
			'items' => $params['result'],
			'properties' => $properties,
		];

		foreach ($params['result']['items'] as $key => $item) {
			$props = $propVals[$item['ID']];
			$item = array_merge($item, $props);
			$params['result']['items'][$key] = $item;
		}
	}
}
