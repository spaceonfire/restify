<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}
/** @var \goldencode\Bitrix\Restify\RestifyCatalogSmartFilterComponent $parentComponent */
$parentComponent = $component->__parent;
$parentComponent->json($arResult);
die();
