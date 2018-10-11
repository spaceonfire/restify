<?

namespace goldencode\Bitrix\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use CSite;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\REQUIRE_ERROR;
use goldencode\Helpers\Bitrix\Tools as BitrixTools;

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

	protected $formatters = [
		'PREVIEW_PICTURE' => 'goldencode\Bitrix\Restify\Formatters\FileFormatter',
		'DETAIL_PICTURE' => 'goldencode\Bitrix\Restify\Formatters\FileFormatter',
		'PICTURE' => 'goldencode\Bitrix\Restify\Formatters\FileFormatter',
		'PSA_LOGOTIP' => 'goldencode\Bitrix\Restify\Formatters\FileFormatter',
		'LOGOTIP' => 'goldencode\Bitrix\Restify\Formatters\FileFormatter',
		'DATE_CREATE' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'TIMESTAMP_X' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'ACTIVE_FROM' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'ACTIVE_TO' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'PERSONAL_BIRTHDAY' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'PERSONAL_BIRTHDAY_DATE' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_INSERT' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_UPDATE' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_PAYED' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_STATUS' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_ALLOW_DELIVERY' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_PAY_BEFORE' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_BILL' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
		'DATE_CANCELED' => 'goldencode\Bitrix\Restify\Formatters\DateFormatter',
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
		global $DB;
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
		$dateFields = array_filter($this->formatters, function($f) {
			return $f === 'goldencode\Bitrix\Restify\Formatters\DateFormatter';
		});
		foreach ($dateFields as $field => $formatter)
			if ($req->data->__isset($field))
				$req->data->__set(
					$field,
					date(
						$DB->DateFormatToPHP(CSite::GetDateFormat()),
						strtotime($req->data->__get($field))
					)
				);

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
		$item = BitrixTools::removeTildaKeys($item);
		$item = $this->decodeSpecialChars($item);

		// Format fields
		foreach ($this->formatters as $field => $formatter) {
			if ($item[$field])
				$item[$field] = call_user_func_array([$formatter, 'format'], [$item[$field]]);
		}

		return $item;
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
