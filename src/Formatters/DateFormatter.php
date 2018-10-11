<?php

namespace goldencode\Bitrix\Restify\Formatters;

class DateFormatter implements IFormatter {
	/**
	 * Convert bitrix date to ISO 8601 format
	 * @param mixed $data
	 * @return string
	 */
	public static function format($data) {
		return date('c', strtotime($data));
	}
}
