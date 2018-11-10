<?php

namespace spaceonfire\Restify;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use spaceonfire\Restify\Executors\UserRest;

if (!Loader::includeModule('spaceonfire.restify')) return false;

Loc::loadLanguageFile(__FILE__);

class RestifyUserComponent extends RouterComponent {
	public function executeComponent() {
		$executor = new UserRest($this->arParams);
		$this->setExecutor($executor);
		$this->cors();
		$this->route('POST /', [$this, 'create']);
		$this->route('GET /', [$this, 'readMany']);
		$this->route('GET /count', [$this, 'count']);
		$this->route('POST /login', [$this, 'login']);
		$this->route('POST /forgotPassword', [$this, 'forgotPassword']);
		$this->route('POST /resetPassword', [$this, 'resetPassword']);
		$this->route('/logout', [$this, 'logout']);
		$this->route('GET /@id', [$this, 'readOne']);
		$this->route('POST /@id', [$this, 'update']);
		$this->route('DELETE /@id', [$this, 'delete']);
		$this->start();
	}
}
