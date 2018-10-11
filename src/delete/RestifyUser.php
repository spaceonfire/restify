<?

namespace goldencode\Bitrix\Restify;

use CGroup;
use CUser;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\CREATE_ERROR;
use goldencode\Bitrix\Restify\Errors\DELETE_ERROR;
use goldencode\Bitrix\Restify\Errors\FORGOT_PASSWORD_ERROR;
use goldencode\Bitrix\Restify\Errors\LOGIN_ERROR;
use goldencode\Bitrix\Restify\Errors\NOT_AUTH;
use goldencode\Bitrix\Restify\Errors\NOT_FOUND;
use goldencode\Bitrix\Restify\Errors\RESET_PASSWORD_ERROR;
use goldencode\Bitrix\Restify\Errors\UPDATE_ERROR;

class RestifyUser extends Core {
	public $iblock;

	/**
	 * RestifyUser constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options = []) {
		parent::__construct();
		$this->requireModules(['main']);

		$this->options['defaults select'] = array_merge(
			$this->options['defaults select'],
			[
				'LOGIN',
				'EMAIL',
				'NAME',
				'LAST_NAME',
				'PERSONAL_GENDER',
				'PERSONAL_MOBILE',
				'PERSONAL_BIRTHDAY',
				'PERSONAL_STATE',
				'PERSONAL_CITY',
				'PERSONAL_ZIP',
				'PERSONAL_STREET',
				'PERSONAL_MAILBOX'
			]
		);
		$this->options = array_merge($this->options, $options);

		$this->formatters = array_merge($this->formatters, $this->options['formatters'] ?? []);

		$manyUri =
			$this->options['prefix'] .
			$this->options['version'] .
			'/User';

		$oneUri = $manyUri . '/@id';

		Flight::route("GET $manyUri", [$this, 'readMany']);
		Flight::route("POST $manyUri", [$this, 'create']);
		Flight::route("POST $manyUri/login", [$this, 'login']);
		Flight::route("POST $manyUri/forgotPassword", [$this, 'forgotPassword']);
		Flight::route("POST $manyUri/resetPassword", [$this, 'resetPassword']);
		Flight::route("$manyUri/logout", [$this, 'logout']);

		Flight::route("GET $oneUri", [$this, 'readOne']);
		Flight::route("POST $oneUri", [$this, 'update']);
		Flight::route("DELETE $oneUri", [$this, 'delete']);
	}

	/**
	 * Get User(s)
	 * @param string|int|null $id User id or login
	 * @throws Exception
	 */
	public function readMany($id = null) {
		$req = $this->request();
		$order = $req->query->__get('order');
		$filter = $req->query->__get('filter');
		$navParams = $req->query->__get('navParams');
		$select = $this->splitSelect($req->query->__get('select'));

		if ($id) {
			global $USER;
			if ($id === 'me') {
				$id = $USER->GetID();
				if (!$id) throw new NOT_AUTH();
			}

			// ID or CODE
			if (is_numeric($id))
				$filter['ID'] = $id;
			else
				$filter['LOGIN'] = $id;

			// get only one item
			$navParams = ['nPageSize' => 1];
		}

		$query = CUser::GetList(
			array_shift(array_keys($order)),
			array_shift(array_values($order)),
			$filter,
			[
				'SELECT' => $select['userFields'],
				'NAV_PARAMS' => $navParams,
				'FIELDS' => $select['default']
			]
		);

		$results = [];
		while ($item = $query->GetNext()) {
			$item = $this->prepareOutput($item);
			$results[] = $item;
		}

		if (!count($results) && $id)
			throw new NOT_FOUND('User with this id or login not found');

		$this->json($id ? array_pop($results) : $results);
	}

	/**
	 * Get user by ID or Login
	 * @param int|string $id
	 * @throws Exception
	 */
	public function readOne($id) {
		$this->readMany($id);
	}

	/**
	 * Create user
	 * @throws CREATE_ERROR
	 * @throws Exception
	 */
	public function create() {
		$req = $this->request();
		$data = $req->data->getData();
		$user = new CUser;
		$userId = $user->Add($data);
		if (!$userId) throw new CREATE_ERROR($user->LAST_ERROR);
		$this->readOne($userId);
	}

	/**
	 * Update user by ID
	 * ID may be passed in url or as body param
	 * @param int|null $id
	 * @throws Exception
	 * @throws UPDATE_ERROR
	 */
	public function update($id = null) {
		$req = $this->request();
		$data = $req->data->getData();

		if (!$id) $id = $data['ID'];
		unset($data['ID']);
		if (!$id) throw new UPDATE_ERROR("Id not provided", 400);

		$user = new CUser;
		if (!$user->Update($id, $data))
			throw new UPDATE_ERROR($user->LAST_ERROR);

		$this->readOne($id);
	}

	/**
	 * Delete user by ID
	 * @param $id
	 * @throws Exception
	 */
	public function delete($id) {
		global $DB;

		$DB->StartTransaction();

		try {
			$result = CUser::Delete($id);
			if (!$result)
				throw new DELETE_ERROR("Cannot delete user with id=$id");
		} catch (Exception $exception) {
			$DB->Rollback();
			throw $exception;
		}

		$DB->Commit();

		$this->success('User successfully deleted');
	}

	/**
	 * Login user
	 * @throws Exception
	 * @throws LOGIN_ERROR
	 */
	public function login() {
		$req = Flight::request();
		$data = $req->data->getData();

		global $USER, $APPLICATION;
		if (!is_object($USER)) $USER = new CUser;
		$result = $USER->Login($data['LOGIN'], $data['PASSWORD'], $data['REMEMBER']);
		$APPLICATION->arAuthResult = $result;
		if ($result !== true) throw new LOGIN_ERROR($result['MESSAGE']);

		$this->readOne($data['LOGIN']);
	}

	/**
	 * Logout user
	 * @throws Exception
	 */
	public function logout() {
		global $USER;
		if (!is_object($USER)) $USER = new CUser;
		$USER->Logout();
		$this->success('User successfully logged out');
	}

	/**
	 * Forgot password
	 * @throws FORGOT_PASSWORD_ERROR
	 */
	public function forgotPassword() {
		$req = Flight::request();
		$data = $req->data->getData();

		global $USER;
		$result = $USER->SendPassword($data['LOGIN'], $data['LOGIN']);
		$result['MESSAGE'] = str_replace('<br>', '', $result['MESSAGE']);
		if ($result['TYPE'] !== 'OK')
			throw new FORGOT_PASSWORD_ERROR($result['MESSAGE']);

		$this->success($result['MESSAGE']);
	}

	/**
	 * Reset password
	 * @throws RESET_PASSWORD_ERROR
	 */
	public function resetPassword() {
		$req = Flight::request();
		$data = $req->data->getData();

		global $USER;
		$result = $USER->ChangePassword(
			$data['LOGIN'],
			$data['CHECKWORD'],
			$data['PASSWORD'],
			$data['PASSWORD']
		);
		$result['MESSAGE'] = str_replace('<br>', '', $result['MESSAGE']);
		if ($result['TYPE'] !== 'OK')
			throw new RESET_PASSWORD_ERROR($result['MESSAGE']);

		$this->success($result['MESSAGE']);
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
	 * Prepare Output
	 * @param array $item
	 * @return array
	 */
	public function prepareOutput(array $item)
	{
		$item = parent::prepareOutput($item);

		// Check is user has admin rights
		$admins = CGroup::GetGroupUser(1);
		$item['IS_ADMIN'] = false;
		if (in_array($item['ID'], $admins))
			$item['IS_ADMIN'] = true;

		// Remove unnecessary fields
		unset($item['PERSONAL_BIRTHDAY_DATE']);

		return $item;
	}
}
