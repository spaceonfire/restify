<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

global $USER;

//if ($USER->IsAdmin())
if (true)
{
	ob_start();
	include __DIR__ . '/swagger-ui/index.html';
	$swaggerUI = ob_get_clean();
	$swaggerUI = str_replace(
		[
			'href="./',
			'src="./',
			'https://petstore.swagger.io/v2/swagger.json',
		],
		[
			'href="' . $templateFolder . '/swagger-ui/',
			'src="' . $templateFolder . '/swagger-ui/',
			'/api/v1/swagger/swagger.json',
		],
		$swaggerUI
	);
	echo  $swaggerUI;
}
