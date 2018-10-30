<?php

namespace goldencode\Bitrix\Restify\Executors;

use Bitrix\Main\Application;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use CMain;
use CUser;
use Emonkak\HttpException\AccessDeniedHttpException;
use Emonkak\HttpException\BadRequestHttpException;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Emonkak\HttpException\NotFoundHttpException;
use Emonkak\HttpException\UnauthorizedHttpException;
use Exception;

class UserRest {
	use RestTrait {
		buildSchema as private _buildSchema;
	}

	private $entity = 'Bitrix\Main\UserTable';

	/**
	 * UserRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws InternalServerErrorHttpException
	 */
	public function __construct($options) {
		$this->loadModules('main');
		$this->setSelectFieldsFromEntityClass();
		$this->setPropertiesFromArray($options);
		$this->registerBasicTransformHandler();
		$this->registerPermissionsCheck();
		$this->buildSchema();
	}

	private function buildSchema() {
		$this->_buildSchema();
		$schema = $this->get('schema');
		$schema['PERSONAL_PHOTO'] = 'file';
		$schema['WORK_LOGO'] = 'file';
		$this->set('schema', $schema);
	}

	public function create() {
		global $USER;
		$id = $USER->Add($this->body);

		if (!$id) {
			throw new BadRequestHttpException($USER->LAST_ERROR);
		}

		return $this->readOne($id);
	}

	public function readMany() {
		$select = $this->splitSelect($this->select);

		$query = CUser::GetList(
			array_shift(array_keys($this->order)),
			array_shift(array_values($this->order)),
			$this->filter,
			[
				'SELECT' => $select['userFields'],
				'NAV_PARAMS' => $this->navParams,
				'FIELDS' => $select['default'],
			]
		);

		$results = [];
		while ($item = $query->GetNext()) {
			$results[] = $item;
		}

		return $results;
	}

	public function readOne($id) {
		$this->registerOneItemTransformHandler();

		$id = $this->getId($id);

		// Set id to filter
		if (is_numeric($id)) {
			$this->filter['ID'] = $id;
		} else {
			$this->filter['LOGIN'] = $id;
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

		$id = $this->getId($id);

		unset($this->body['ID']);
		unset($this->body['IBLOCK_ID']);

		global $USER;
		if (!$USER->Update($id, $this->body)) {
			throw new BadRequestHttpException($USER->LAST_ERROR);
		}

		return $this->readOne($id);
	}

	public function delete($id) {
		$this->registerOneItemTransformHandler();

		global $APPLICATION, $DB;
		$DB->StartTransaction();

		try {
			$id = $this->getId($id);
			$result = CUser::Delete($id);
			if (!$result) {
				throw new InternalServerErrorHttpException($APPLICATION->LAST_ERROR);
			}
		} catch (Exception $exception) {
			$DB->Rollback();
			throw $exception;
		}

		$DB->Commit();

		return [
			$this->success('User successfully deleted'),
		];
	}

	public function count() {
		$this->registerOneItemTransformHandler();

		$this->select = ['ID'];

		$select = $this->splitSelect($this->select);

		$query = CUser::GetList(
			array_shift(array_keys($this->order)),
			array_shift(array_values($this->order)),
			$this->filter,
			[
				'SELECT' => $select['userFields'],
				'NAV_PARAMS' => $this->navParams,
				'FIELDS' => $select['default'],
			]
		);

		$count = $query->SelectedRowsCount();

		return [
			[
				'count' => $count,
			],
		];
	}

	/**
	 * Login user
	 * @throws UnauthorizedHttpException
	 */
	public function login() {
		global $USER, $APPLICATION;

		$result = $USER->Login($this->body['LOGIN'], $this->body['PASSWORD'], $this->body['REMEMBER']);
		$APPLICATION->arAuthResult = $result;
		if ($result !== true) {
			throw new UnauthorizedHttpException($result['MESSAGE']);
		}

		return $this->readOne($this->body['LOGIN']);
	}

	/**
	 * Logout user
	 */
	public function logout() {
		$this->registerOneItemTransformHandler();

		global $USER;
		$USER->Logout();

		return [
			$this->success(Loc::getMessage('LOGOUT_MESSAGE')),
		];
	}

	/**
	 * Forgot password
	 * @throws BadRequestHttpException
	 */
	public function forgotPassword() {
		$this->registerOneItemTransformHandler();
		global $USER;
		$result = $USER->SendPassword($this->body['LOGIN'], $this->body['LOGIN']);
		$result['MESSAGE'] = str_replace('<br>', '', $result['MESSAGE']);

		if ($result['TYPE'] !== 'OK') {
			throw new BadRequestHttpException($result['MESSAGE']);
		}

		return [
			$this->success($result['MESSAGE']),
		];
	}

	/**
	 * Reset password
	 * @throws BadRequestHttpException
	 */
	public function resetPassword() {
		$this->registerOneItemTransformHandler();
		global $USER;
		$result = $USER->ChangePassword(
			$this->body['LOGIN'],
			$this->body['CHECKWORD'],
			$this->body['PASSWORD'],
			$this->body['PASSWORD']
		);
		$result['MESSAGE'] = str_replace('<br>', '', $result['MESSAGE']);

		if ($result['TYPE'] !== 'OK') {
			throw new BadRequestHttpException($result['MESSAGE']);
		}

		return [
			$this->success($result['MESSAGE']),
		];
	}

	/**
	 * Split select fields on UserFields and Default fields
	 * @param array $select
	 * @return array
	 */
	private function splitSelect(array $select) {
		$userFields = array_filter($select, function($field) {
			return strpos($field, 'UF') === 0;
		});
		$defaultFields = array_filter($select, function($field) {
			return strpos($field, 'UF') !== 0;
		});
		return [
			'userFields' => $userFields,
			'default' => $defaultFields
		];
	}

	/**
	 * Get user id
	 * @param string|int $id user id or login or 'me' alias
	 * @return int user id
	 * @throws UnauthorizedHttpException if me alias used by user unauthorized
	 * @throws NotFoundHttpException no user found with this login
	 */
	private function getId($id) {
		global $USER;

		// Convert me to current user id
		if ($id === 'me') {
			$id = $USER->GetID();
			if (!$id) {
				throw new UnauthorizedHttpException();
			}
		}

		// Number is id
		if (is_numeric($id) && (int) $id > 0) {
			return (int) $id;
		}

		// Find by login
		$tmpBy = 'LOGIN';
		$tmpOrder = 'ASC';
		$user = CUser::GetList(
			$tmpBy,
			$tmpOrder,
			array_merge($this->filter, ['LOGIN' => $id]),
			[
				'FIELDS' => [
					'ID',
				],
			]
		)->Fetch();
		if ($user) {
			return (int) $user['ID'];
		}

		throw new NotFoundHttpException();
	}

	private function registerPermissionsCheck() {
		global $goldenCodeRestify;
		$events = [
			'pre:update',
			'pre:delete',
			'pre:readMany',
			'pre:readOne',
		];

		foreach ($events as $event) {
			EventManager::getInstance()->addEventHandler(
				$goldenCodeRestify->getId(),
				$event,
				[$this, 'checkPermissions']
			);
		}
	}

	public function checkPermissions(Event $event) {
		global $USER;
		$permissions = CMain::GetUserRight('main');
		$eventType = $event->getEventType();
		$isWrite = in_array($eventType, ['pre:update', 'pre:delete']);

		if (!$USER->GetID()) {
			throw new UnauthorizedHttpException();
		}

		switch ($permissions) {
			case 'W': {
				// Full access, skip check
				return true;
				break;
			}

			case 'V': {
				// Can read all data and change profiles by some groups
				if ($isWrite) {
					$this->filter = [
						'GROUPS_ID' => \CGroup::GetSubordinateGroups($USER->GetUserGroupArray()),
					];
				}
				break;
			}

			case 'T': {
				// Can read all data and change self profile
				if ($isWrite) {
					$this->filter = [
						'ID' => $USER->GetID(),
					];
				}
			}

			case 'R': {
				// Can only read all data
				if ($isWrite) {
					throw new AccessDeniedHttpException();
				}
			}

			case 'P': {
				// Can read and change only self profile
				$this->filter = [
					'ID' => $USER->GetID(),
				];
				break;
			}

			default: {
				throw new AccessDeniedHttpException();
				break;
			}
		}
	}
}
