<?php

namespace goldencode\Bitrix\Restify;

use Exception;
use Flight;
use Bitrix\Main\Event;
use Bitrix\Main\Localization\Loc;
use Emonkak\HttpException\HttpException;
use Emonkak\HttpException\NotFoundHttpException;

Loc::loadLanguageFile(__FILE__);

abstract class RouterComponent extends \CBitrixComponent {
	/**
	 * @var int JSON encode options
	 */
	private $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT;

	/**
	 * @var int HTTP Response status code
	 */
	private $statusCode = 200;

	/**
	 * @var Executors\IExecutor Executor object
	 */
	private $executor;

	public function __construct() {
		parent::__construct();
		Flight::map('error', [$this, 'errorHandler']);
		Flight::map('notFound', [$this, 'notFound']);
	}

	/**
	 * Middleware wrapper around executor methods
	 * @param string $method
	 * @param array $arguments
	 */
	public function __call($method, $arguments) {
		// Throw 404 if method not implemented
		if (!$this->executor || !method_exists($this->executor, $method)) {
			throw new NotFoundHttpException();
		}

		$this->executor->prepareQuery();

		$this->sendEvent('pre:any');

		$this->sendEvent('pre:' . $method);

		// TODO: cache executor call result
		$this->arResult = call_user_func_array([$this->executor, $method], $arguments);

		$this->sendEvent('post:' . $method);

		$this->sendEvent('transform');

		$this->sendEvent('pre:response');
		$this->json($this->arResult);
		$this->sendEvent('post:response');

		$this->sendEvent('post:any');
	}

	/**
	 * Send server response with JSON
	 * @param mixed $data response data
	 */
	public function json($data) {
		global $APPLICATION;
		$APPLICATION->RestartBuffer();
		Flight::json($data, $this->statusCode, true, SITE_CHARSET, $this->jsonOptions);
	}

	/**
	 * Send server error
	 * @param Exception $exception
	 */
	public function errorHandler($exception) {
		$this->abortResultCache();

		$errorCode = array_pop(explode('\\', get_class($exception)));

		try {
			throw $exception;
		} catch (HttpException $_exception) {
			$errorCode = str_replace('HttpException', '', $errorCode);
			$this->statusCode = $exception->getStatusCode();
			Flight::response()->header($exception->getHeaders());
		} catch (Exception $_exception) {
			$this->statusCode = 500;
		}

		$this->arResult = [
			'error' => [
				'code' => $errorCode,
				'message' => $exception->getMessage() ?: Loc::getMessage('DEFAULT_ERROR_MESSAGE_' . $this->statusCode),
			],
		];

		$this->json($this->arResult);
		// TODO: optionally log rest api errors
	}

	public function executeComponent() {
		$this->cors();
		$this->route('POST /', [$this, 'create']);
		$this->route('GET /', [$this, 'readMany']);
		$this->route('GET /count', [$this, 'count']);
		$this->route('GET /@id', [$this, 'readOne']);
		$this->route('POST /@id', [$this, 'update']);
		$this->route('DELETE /@id', [$this, 'delete']);
		$this->start();
	}

	private function sendEvent($name) {
		global $goldenCodeRestify;
		$preAnyEvent = new Event($goldenCodeRestify->getId(), $name, [
			'executor' => &$this->executor,
			'result' => &$this->arResult,
			'params' => &$this->arParams,
			'statusCode' => &$this->statusCode,
		]);
		$preAnyEvent->send();
	}

	/**
	 * @return object
	 */
	public function getExecutor() {
		return $this->executor;
	}

	/**
	 * @param Executors\IExecutor $executor
	 */
	public function setExecutor($executor) {
		$this->executor = $executor;
		$this->executor->set('component', $this);
	}

	/**
	 * Set Flight route
	 * @param string $pattern
	 * @param callable $callback
	 */
	public function route($pattern, callable $callback) {
		Flight::route($pattern, $callback);
	}

	/**
	 * Start Flight router
	 */
	public function start() {
		Flight::start();
	}

	/**
	 * Enable CORS middleware
	 */
	public function cors() {
		// TODO: get values for headers from options
		$this->route('*', function () {
			Flight::response()->header('Access-Control-Allow-Origin', '*');
			return true;
		});

		$this->route('OPTIONS *', function () {
			Flight::response()
				->header([
					'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
					'Access-Control-Max-Age' => '1728000',
					'Access-Control-Allow-Headers' => '*',
					'Content-Length' => '0',
				])
				->status(204)
				->send();
			die();
		});
	}

	/**
	 * @param int $jsonOptions
	 */
	public function setJsonOptions($jsonOptions) {
		$this->jsonOptions = $jsonOptions;
	}
}
