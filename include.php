<?php

use spaceonfire\BMF\Module;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/options.php');
Loc::loadMessages(__DIR__ . '/install/index.php');
Loc::loadMessages(__DIR__ . '/options.php');

global $goldenCodeRestify;
$goldenCodeRestify = new Module([
	'MODULE_ID' => 'goldencode.restify',
	'MODULE_VERSION' => '1.0.0',
	'MODULE_VERSION_DATE' => '2018-10-10',
]);

$goldenCodeRestify->logger->setAuditTypeId('RESTIFY');

$goldenCodeRestify->options->addTabs([
	'default' => [
		'TAB' => Loc::getMessage('DEFAULT_TAB_NAME'),
		'TITLE' => Loc::getMessage('DEFAULT_TAB_TITLE', [
			'#MODULE_NAME#' => Loc::getMessage('RESTIFY_MODULE_NAME'),
		]),
	],
]);
