<?php

namespace goldencode\Bitrix\Restify\Executors;

/**
 * Interface ExecutorInterface
 * @property \CBitrixComponent $component bitrix component instance
 */
interface IExecutor {
	public function get($name);
	public function set($name, $value);
	public function prepareQuery();
}
