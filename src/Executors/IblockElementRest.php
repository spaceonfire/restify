<?php

namespace goldencode\Bitrix\Restify\Executors;

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use CCatalog;
use CIBlock;
use CIBlockElement;
use CIBlockFindTools;
use CPrice;
use Emonkak\HttpException\AccessDeniedHttpException;
use Emonkak\HttpException\BadRequestHttpException;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Emonkak\HttpException\NotFoundHttpException;
use Exception;

class IblockElementRest {
	use RestTrait {
		prepareQuery as private _prepareQuery;
		buildSchema as private _buildSchema;
	}

	protected $iblockId;
	protected $prices = [];

	private $catalog = false;
	private $permissions = [];
	private $entity = 'Bitrix\Iblock\ElementTable';

	/**
	 * IblockElementRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws InternalServerErrorHttpException
	 */
	public function __construct($options) {
		$this->loadModules('iblock');

		if (!$options['iblockId']) {
			throw new InternalServerErrorHttpException(Loc::getMessage('REQUIRED_PROPERTY', [
				'#PROPERTY#' => 'iblockId',
			]));
		}

		$this->setSelectFieldsFromEntityClass();
		$this->setPropertiesFromArray($options);

		// Support catalog
		if ($this->loadModules('catalog', false)) {
			$this->catalog = (bool) CCatalog::GetByID($this->iblockId);
			if ($this->catalog) {
				$this->registerCatalogTransform();
			}
		}

		$this->registerBasicTransformHandler();
		$this->registerPermissionsCheck();
		$this->buildSchema();
	}

	/**
	 * @throws Exception
	 */
	private function buildSchema() {
		$this->_buildSchema();

		$schema = $this->get('schema');

		$schema['PREVIEW_PICTURE'] = 'file';
		$schema['DETAIL_PICTURE'] = 'file';

		// TODO: cache dependently from iblock
		$propsQ = CIBlock::GetProperties($this->iblockId, [], ['ACTIVE' => 'Y']);
		while ($prop = $propsQ->Fetch()) {
			$type = strtolower($prop['USER_TYPE'] ?: $prop['PROPERTY_TYPE']);
			switch ($type) {
				case 'f': $type = 'file'; break;
				case 's': $type = 'string'; break;
				case 'n': $type = 'number'; break;
				case 'e': $type = 'element'; break;
				case 'elist': $type = 'elementlist'; break;
				case 'eautocomplete': $type = 'elementautocomplete'; break;
			}
			$schema['PROPERTY_' . $prop['CODE']] = $type;
		}

		$this->set('schema', $schema);
	}

	public function create() {
		$el = new CIBlockElement;
		$id = $el->Add($this->body);

		if (!$id) {
			throw new BadRequestHttpException($el->LAST_ERROR);
		}

		return $this->readOne($id);
	}

	public function readMany() {
		$query = CIBlockElement::GetList(
			$this->order,
			$this->filter,
			false,
			$this->navParams,
			$this->select
		);

		$results = [];
		while ($item = $query->GetNext()) {
			$results[] = $item;
		}

		return $results;
	}

	public function readOne($id) {
		$this->registerOneItemTransformHandler();

		// Set id to filter
		if (is_numeric($id)) {
			$this->filter['ID'] = $id;
		} else {
			$this->filter['CODE'] = $id;
		}

		// Get only one item
		$this->navParams = ['nPageSize' => 1];

		$results = $this->readMany();

		if (!count($results)) {
			throw new NotFoundHttpException();
		}

		return $results;
	}

	public function update($id = null) {
		if (!$id) {
			$id = $this->body['ID'];
		}

		if (!$id) {
			throw new NotFoundHttpException();
		}

		$id = CIBlockFindTools::GetElementID($id, $id, null, null, $this->filter);

		unset($this->body['ID']);
		unset($this->body['IBLOCK_ID']);

		$el = new CIBlockElement;
		if (!$el->Update($id, $this->body)) {
			throw new BadRequestHttpException($el->LAST_ERROR);
		}

		return $this->readOne($id);
	}

	public function delete($id) {
		$this->registerOneItemTransformHandler();

		global $APPLICATION, $DB;
		$DB->StartTransaction();

		try {
			$id = CIBlockFindTools::GetElementID($id, $id, null, null, $this->filter);
			$result = CIBlockElement::Delete($id);
			if (!$result) {
				throw new InternalServerErrorHttpException($APPLICATION->LAST_ERROR);
			}
		} catch (Exception $exception) {
			$DB->Rollback();
			throw $exception;
		}

		$DB->Commit();

		return [
			$this->success('Element successfully deleted'),
		];
	}

	public function count() {
		$this->registerOneItemTransformHandler();

		$this->select = ['ID'];

		$query = CIBlockElement::GetList(
			$this->order,
			$this->filter,
			false,
			$this->navParams,
			$this->select
		);
		$count = $query->SelectedRowsCount();

		return [
			[
				'count' => $count,
			],
		];
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

		// Force set IBLOCK_ID to body
		if (!empty($this->body)) {
			$this->body['IBLOCK_ID'] = $this->iblockId;
		}

		// Extend select with properties
		if (in_array('*', $this->select)) {
			$this->select = array_merge(
				$this->select,
				array_filter(array_keys($this->get('schema')), function ($path) {
					return strpos($path, 'PROPERTY_') !== false;
				})
			);
		}
	}

	private function registerCatalogTransform() {
		global $goldenCodeRestify;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$goldenCodeRestify->getId(),
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

	private function registerPermissionsCheck() {
		global $goldenCodeRestify;
		$events = [
			'pre:create',
			'pre:update',
			'pre:delete',
		];

		foreach ($events as $event) {
			EventManager::getInstance()->addEventHandler(
				$goldenCodeRestify->getId(),
				$event,
				[$this, 'checkPermissions']
			);
		}
	}

	public function checkPermissions() {
		global $USER;

		$this->permissions = CIBlock::GetGroupPermissions($this->iblockId);
		$permissions = $this->permissions;

		$userGroupsPermissions = array_map(function ($group) use ($permissions) {
			return $permissions[$group];
		}, $USER->GetUserGroupArray());

		$canWrite = in_array('W', $userGroupsPermissions) || in_array('X', $userGroupsPermissions);

		if (!$canWrite) {
			throw new AccessDeniedHttpException();
		}
	}
}
