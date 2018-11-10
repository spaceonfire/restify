<?php

namespace spaceonfire\Restify\Formatters;

interface IFormatter {
	/**
	 * Format data
	 * @param mixed $data
	 * @return mixed
	 */
	public static function format($data);
}
