<?php

namespace spaceonfire\Restify\Formatters;

use CFile;

class FileFormatter implements IFormatter {
	/**
	 * Get bitrix file
	 * @param string|int $fileId
	 * @return array
	 */
	public static function format($fileId) {
		$rawFile = CFile::GetFileArray($fileId);

		$selectFields = [
			'ID',
			'SRC',
			'HEIGHT',
			'WIDTH',
			'FILE_SIZE',
			'CONTENT_TYPE',
			'ORIGINAL_NAME',
			'DESCRIPTION'
		];

		$file = [];
		foreach ($selectFields as $field) {
			$file[$field] = $rawFile[$field];
		}

		// TODO: make full path optional
		$file['SRC'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $file['SRC'];

		return $file;
	}
}
