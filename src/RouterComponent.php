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
	 * @var object Executor object
	 */
	private $executor;

	public function __construct() {
		parent::__construct();
		Flight::map('error', [$this, 'errorHandler']);
	}

	/**
	 * Middleware wrapper around executor methods
	 * @param string $method
	 * @param array $arguments
	 */
	public function __call($method, $arguments) {
		// Throw 404 if method not implemented
		if (!$this->executor || !method_exists($this->executor, $method)) {
			throw new NotFoundHttpException(Loc::getMessage('NOT_FOUND_ERROR'));
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
		} catch (Exception $_exception) {
			$this->statusCode = 500;
		}

		$this->arResult = [
			'error' => [
				'code' => $errorCode,
				'message' => $exception->getMessage(),
			],
		];

		// TODO: send custom headers
		// $headers = $exception->getHeaders();

		$this->json($this->arResult);
		// TODO: optionally log rest api errors
	}

	public function executeComponent() {
		Flight::route('POST /', [$this, 'create']);
		Flight::route('GET /', [$this, 'readMany']);
		Flight::route('GET /count', [$this, 'count']);
		Flight::route('GET /@id', [$this, 'readOne']);
		Flight::route('POST /@id', [$this, 'update']);
		Flight::route('DELETE /@id', [$this, 'delete']);
		Flight::start();
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
	 * @param object $executor
	 */
	public function setExecutor($executor): void {
		$this->executor = $executor;
		$this->executor->set('component', $this);
	}
}
