<?php
require_once __DIR__ . '/include.php';

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Add documentation tab
if (file_exists(__DIR__ . '/readme.md')) {
	global $SPACEONFIRE_RESTIFY;

	$SPACEONFIRE_RESTIFY->options->addTab('docs', [
		'TAB' => Loc::getMessage('DOCS_TAB_NAME'),
		'TITLE' => Loc::getMessage('DOCS_TAB_TITLE', [
			'#MODULE_NAME#' => Loc::getMessage('RESTIFY_MODULE_NAME'),
		]),
	]);
	$SPACEONFIRE_RESTIFY->options->addOption('DOCUMENTATION', [
		'type' => 'html',
		'tab' => 'docs',
		'html' => Parsedown::instance()->text(file_get_contents(__DIR__ . '/readme.md')),
	]);
}

$SPACEONFIRE_RESTIFY->showOptionsForm();
