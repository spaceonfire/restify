<?php

require_once __DIR__ . '/../include.php';

use Bitrix\Main\Localization\Loc;
use spaceonfire\BMF\ModuleInstaller;

Loc::loadMessages(__FILE__);

class goldencode_restify extends CModule
{
	var $MODULE_ID = 'goldencode.restify';

	use ModuleInstaller;

	public function __construct() {
		$arModuleVersion = [];

		include __DIR__ . '/version.php';

		if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
			$this->MODULE_VERSION = $arModuleVersion['VERSION'];
			$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		}

		$this->MODULE_ID = 'goldencode.restify';
		$this->MODULE_NAME = Loc::getMessage('RESTIFY_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('RESTIFY_MODULE_DESCRIPTION');
		$this->MODULE_GROUP_RIGHTS = 'N';
		$this->PARTNER_NAME = Loc::getMessage('RESTIFY_MODULE_PARTNER_NAME');
		$this->PARTNER_URI = 'https://zolotoykod.ru/';

		$this->INSTALLER_DIR = __DIR__;
		$this->INSTALL_PATHS = [
			'bitrix',
		];

		if ($this->isDevelopmentMode()) {
			$this->DEV_LINKS = [
				$_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/goldencode/restify.iblock.element' => __DIR__ . '/bitrix/components/goldencode/restify.iblock.element',
				$_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/goldencode/restify.iblock.section' => __DIR__ . '/bitrix/components/goldencode/restify.iblock.section',
				$_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/goldencode/restify.main.user' => __DIR__ . '/bitrix/components/goldencode/restify.main.user',
			];
		}
	}

	public function installDB() {
		RegisterModuleDependences('main', 'OnEventLogGetAuditTypes', $this->MODULE_ID, 'goldencode\Bitrix\Restify\EventLogAuditType', 'registerAuditTypes');
	}

	public function uninstallDB() {
		UnRegisterModuleDependences('main', 'OnEventLogGetAuditTypes', $this->MODULE_ID, 'goldencode\Bitrix\Restify\EventLogAuditType', 'registerAuditTypes');
	}
}
