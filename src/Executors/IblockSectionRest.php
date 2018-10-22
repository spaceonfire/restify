<?php

namespace goldencode\Bitrix\Restify\Executors;

use Bitrix\Main\Application;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use CIBlock;
use CIBlockSection;
use CIBlockFindTools;
use Emonkak\HttpException\AccessDeniedHttpException;
use Emonkak\HttpException\BadRequestHttpException;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Emonkak\HttpException\NotFoundHttpException;
use Exception;

class IblockSectionRest {
	use RestTrait { prepareQuery as private _prepareQuery; }

	protected $iblockId;
	private $permissions = [];
	private $entity = 'Bitrix\Iblock\SectionTable';

	/**
	 * IblockSectionRest constructor
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

		$this->filter = [
			'ACTIVE' => 'Y',
			'GLOBAL_ACTIVE' => 'Y',
		];

		$this->setSelectFieldsFromEntityClass();
		$this->setPropertiesFromArray($options);
		$this->registerBasicTransformHandler();
		$this->registerPermissionsCheck();
		$this->registerSectionTransform();
		$this->buildSchema();
	}

	private function buildSchema() {
		$connection = Application::getConnection();

		$schema = [];

		// TODO: cache independently from iblock
		$result = $connection->query('describe b_iblock_section')->fetchAll();
		foreach ($result as $value) {
			$schema[$value['Field']] = $value['Type'];
		}
		$schema['PICTURE'] = 'file';
		$schema['DETAIL_PICTURE'] = 'file';

		$this->set('schema', $schema);
	}

	public function create() {
		$section = new CIBlockSection;
		$sectionId = $section->Add($this->body);

		if (!$sectionId) {
			throw new BadRequestHttpException($section->LAST_ERROR);
		}

		return $this->readOne($sectionId);
	}

	public function readMany() {
		$query = CIBlockSection::GetList(
			$this->order,
			$this->filter,
			false,
			$this->select,
			$this->navParams
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

		$id = CIBlockFindTools::GetSectionID($id, $id, $this->filter);

		unset($this->body['ID']);
		unset($this->body['IBLOCK_ID']);

		$section = new CIBlockSection;
		if (!$section->Update($id, $this->body)) {
			throw new BadRequestHttpException($section->LAST_ERROR);
		}

		return $this->readOne($id);
	}

	public function delete($id) {
		$this->registerOneItemTransformHandler();

		global $APPLICATION, $DB;
		$DB->StartTransaction();

		try {
			$id = CIBlockFindTools::GetElementID($id, $id, null, null, $this->filter);
			$result = CIBlockSection::Delete($id);
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

		$query = CIBlockSection::GetList(
			$this->order,
			$this->filter,
			false,
			$this->select,
			$this->navParams
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

	private function registerSectionTransform() {
		global $goldenCodeRestify;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$goldenCodeRestify->getId(),
			'transform',
			[$this, 'sectionTransform']
		);
	}

	public function sectionTransform(Event $event) {
		$params = $event->getParameters();
		foreach ($params['result'] as $key => $item) {
			$item['ELEMENTS_COUNT'] = (int) (new CIBlockSection())->GetSectionElementsCount(
				$item['ID'],
				['CNT_ACTIVE' => 'Y']
			);

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
