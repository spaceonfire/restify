<?php

namespace spaceonfire\Restify\Executors;

use Bitrix\Main\Localization\Loc;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Exception;

class SearchRest implements IExecutor {
	use RestTrait;

	/** @var \CSearch | null search instance */
	private $obSearch = null;

	/**
	 * SearchRest constructor
	 * @param array $options executor options
	 * @throws \Bitrix\Main\LoaderException
	 * @throws Exception
	 */
	public function __construct($options) {
		$this->loadModules(['search']);
		$this->setPropertiesFromArray($options);
		$this->registerBasicTransformHandler();
		$this->initSearchInstance();
		$this->buildSchema();
	}

	private function buildSchema() {
		$schema = [];
		$schema['DATE_FROM'] = 'date';
		$schema['DATE_TO'] = 'date';
		$schema['FULL_DATE_CHANGE'] = 'date';
		$schema['DATE_CHANGE'] = 'date';
		$this->set('schema', $schema);
	}

	private function initSearchInstance() {
		$this->obSearch = new \CSearch();
		$this->obSearch->SetOptions([
			'ERROR_ON_EMPTY_STEM' => false,
		]);
	}

	private function setSearch($arParamsEx = []) {
		$this->obSearch->Search(
			[
				'QUERY' => $_REQUEST['q'],
				'CHECK_DATES' => 'Y',
			],
			$this->order,
			$arParamsEx
		);
	}

	public function readMany() {
		$this->setSearch();

		if (!$this->obSearch->selectedRowsCount()) {
			$this->setSearch(['STEMMING' => false]);
		}

		$this->obSearch->NavStart($this->navParams);

		$results = [];
		while ($item = $this->obSearch->Fetch()) {
			$results[] = $item;
		}

		return $results;
	}

	public function count() {
		$this->registerOneItemTransformHandler();

		$this->setSearch();

		if (!($count = $this->obSearch->selectedRowsCount())) {
			$this->setSearch(['STEMMING' => false]);
			$count = $this->obSearch->selectedRowsCount();
		}

		return [
			[
				'count' => $count,
			],
		];
	}
}
