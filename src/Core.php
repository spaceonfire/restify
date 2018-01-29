<?

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use CFile;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\REQUIRE_ERROR;

class Core {
	protected $options = [
		'prefix' => '/api',
		'version' => '/v1',
		'jsonOptions' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT,
		'defaults order' => ['SORT' => 'ASC'],
		'defaults filter' => ['ACTIVE' => 'Y'],
		'defaults navParams' => [],
		'defaults select' => [
			'ID',
			'NAME',
			'CODE'
		]
	];
	protected $hooks = [
		'pre:all' => [],
		'pre:create' => [],
		'pre:read' => [],
		'pre:update' => [],
		'pre:delete' => [],
		'post:create' => [],
		'post:read' => [],
		'post:update' => [],
		'post:delete' => [],
		'post:all' => [],
	];

	protected $fileFields = [
		'PREVIEW_PICTURE',
		'DETAIL_PICTURE',
		'PICTURE',
		'PSA_LOGOTIP',
		'LOGOTIP'
	];

	protected $dateFields = [
		'DATE_CREATE',
		'TIMESTAMP_X',
		'ACTIVE_FROM',
		'ACTIVE_TO',
		'PERSONAL_BIRTHDAY',
		'PERSONAL_BIRTHDAY_DATE',
		'DATE_INSERT',
		'DATE_UPDATE',
		'DATE_PAYED',
		'DATE_STATUS',
		'DATE_ALLOW_DELIVERY',
		'DATE_PAY_BEFORE',
		'DATE_BILL',
		'DATE_CANCELED',
	];

	public function __construct()
	{
		// set custom error handler
		Flight::map('error', [$this, 'error']);
	}


	/**
	 * Add hook function
	 * @param string $hook
	 * @param callable $function
	 * @throws \Exception
	 */
	public function on($hook, callable $function) {
		if (!array_key_exists($hook, $this->hooks))
			throw new \Exception("Unknown hook: $hook");
		array_push($this->hooks[$hook], $function);
	}


	/**
	 * Run hook's functions sequence
	 *
	 * @param string $hook
	 * @throws \Exception
	 */
	protected function execHook($hook) {
		if (!array_key_exists($hook, $this->hooks))
			throw new \Exception("Unknown hook: $hook");
		foreach ($this->hooks[$hook] as $func) call_user_func($func);
	}


	/**
	 * Load bitrix modules or response with error
	 * @param array $modules
	 * @throws REQUIRE_ERROR
	 * @throws LoaderException
	 */
	public function requireModules(array $modules) {
		foreach ($modules as $module) {
			$loaded = Loader::includeModule($module);
			if (!$loaded) throw new REQUIRE_ERROR("$module not loaded!");
		}
	}


	/**
	 * Get request object
	 * @return \flight\net\Request
	 */
	public function request() {
		$req = Flight::request();

		foreach (['order', 'filter', 'select', 'navParams'] as $field) {
			if (empty($req->query[$field])) {
				$req->query[$field] = $this->options["defaults $field"];
				continue;
			}

			if (json_decode($req->query[$field], true))
				$req->query[$field] = json_decode($req->query[$field], true);

			if (!is_array($req->query[$field]))
				$req->query[$field] = [$req->query[$field]];
		}

		// delete iblock props from filter
		unset($req->query['filter']['IBLOCK_ID']);
		unset($req->query['filter']['IBLOCK_CODE']);
		unset($req->query['filter']['IBLOCK_SITE_ID']);
		unset($req->query['filter']['IBLOCK_TYPE']);

		// get files
		if (strpos(strtolower($req->type), 'multipart/form-data') !== false) {
			$data = $req->data->current();

			if (json_decode($data))
				$data = json_decode($data, true);
			if (is_array($data)) {
				while ($file = $req->files->current()) {
					$data[$req->files->key()] = $file;
					$req->files->next();
				}
				$req->data->setData($data);
			}
		}

		// parse dates
		foreach ($this->dateFields as $field)
			if ($req->data->__isset($field))
				$req->data->__set($field, date('d.m.Y  H:i:s', strtotime($req->data->__get($field))));

		return $req;
	}


	/**
	 * Send server response with JSON
	 * @param mixed $data response data
	 * @param int $code HTTP response code
	 */
	public function json($data, $code = 200) {
		Flight::json($data, $code, true, 'utf-8', $this->options['jsonOptions']);
	}


	/**
	 * Send server error
	 * @param Exception $error
	 */
	public function error($error) {
		$response = [
			'error' => [
				'code' => array_pop(explode('\\', get_class($error))),
				'message' => $error->getMessage()
			]
		];
		$code = $error->getCode();
		if ($code < 400) $code = 500;
		$this->json($response, $code);
	}


	/**
	 * Send server success message
	 * @param mixed $message
	 */
	public function success($message) {
		$this->json([
			'result' => 'ok',
			'message' => $message
		]);
	}


	/**
	 * Prepare Output
	 * @param array $item
	 * @return array
	 */
	public function prepareOutput(array $item) {
		$item = $this->removeTildaKeys($item);
		$item = $this->decodeSpecialChars($item);

		// Files
		foreach ($this->fileFields as $filePath)
			if ($item[$filePath])
				$item[$filePath] = $this->getFile($item[$filePath]);

		// Format dates
		foreach ($this->dateFields as $datePath)
			if ($item[$datePath])
				$item[$datePath] = date('c', strtotime($item[$datePath]));

		return $item;
	}


	/**
	 * Remove keys started with tilda (~)
	 * @param array $data
	 * @return array
	 */
	protected function removeTildaKeys(array $data) {
		$deleteKeys = array_filter(array_keys($data), function($key) {
			return strpos($key, '~') === 0;
		});
		foreach ($deleteKeys as $key) unset($data[$key]);
		return $data;
	}


	/**
	 * Get bitrix file, remove tilda keys and pick model fields
	 * @param $fileId
	 * @return array
	 */
	protected function getFile($fileId) {
		$rawFile = CFile::GetFileArray($fileId);
		$rawFile = $this->removeTildaKeys($rawFile);

		$selectFields = [
			'ID',
			'SRC',
			'HEIGHT',
			'WIDTH',
			'FILE_SIZE',
			'CONTENT_TYPE',
			'ORIGINAL_NAME',
			'DESCRIPTION'
		];

		$file = [];
		foreach ($selectFields as $field)
			$file[$field] = $rawFile[$field];

		return $file;
	}


	/**
	 * Decode html entities and special chars in content fields
	 * @param array $item
	 * @return array
	 */
	protected function decodeSpecialChars($item) {
		$contentFields = ['NAME', 'PREVIEW_TEXT', 'DETAIL_TEXT'];
		foreach ($contentFields as $field)
			if ($item[$field])
				$item[$field] = html_entity_decode($item[$field], ENT_QUOTES | ENT_HTML5);
		return $item;
	}
}
