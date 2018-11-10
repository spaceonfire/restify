<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}
/** @var \spaceonfire\Restify\RestifyCatalogSmartFilterComponent $parentComponent */
$parentComponent = $component->__parent;
$parentComponent->json($arResult);
die();
