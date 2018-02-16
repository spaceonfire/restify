<?php

namespace goldencode\Bitrix\Restify\Formatter;

interface FormatterInterface {
	/**
	 * Format data
	 * @param mixed $data
	 * @return mixed
	 */
	public static function format($data);
}
