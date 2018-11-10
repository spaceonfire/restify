<?php

namespace spaceonfire\Restify\Executors;

use Exception;
use CSite;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Emonkak\HttpException\InternalServerErrorHttpException;
use Flight;
use flight\net\Request;
use goldencode\Helpers\Bitrix\Tools;
use ReflectionObject;
use ReflectionProperty;

trait RestTrait {
	/**
	 * @var array Bitrix query order
	 */
	public $order = ['SORT' => 'ASC'];

	/**
	 * @var array Bitrix query filter
	 */
	public $filter = ['ACTIVE' => 'Y'];

	/**
	 * @var array Bitrix query nav params
	 */
	public $navParams = [
		'nPageSize' => 25,
		'checkOutOfRange' => true,
	];

	/**
	 * @var int Bitrix ORM query limit
	 */
	public $limit = null;

	/**
	 * @var int Bitrix ORM query offset
	 */
	public $offset = 0;

	/**
	 * @var string symbol to divide nested select fields
	 */
	public $ormNestedSelectSeparator = ':';

	/**
	 * @var array Bitrix query select fields
	 */
	public $select = ['*'];

	/**
	 * @var array Parsed request body
	 */
	public $body = [];

	/**
	 * @var Context Bitrix Application Context
	 */
	private $context;

	/**
	 * @var Request Bitrix Application Context
	 */
	private $flightRequest;

	/**
	 * @var array Entity schema
	 */
	private $schema = [];

	/**
	 * @var array Map formatters to schema field types
	 */
	private $formatters = [
		'spaceonfire\Restify\Formatters\DateFormatter' => 'date',
		'spaceonfire\Restify\Formatters\FileFormatter' => 'file',
	];

	/**
	 * @var \CBitrixComponent bitrix component instance
	 */
	private $component;

	/**
	 * Get property value
	 * @param string $name
	 * @return mixed
	 */
	public function get($name) {
		return $this->{$name};
	}

	/**
	 * Set property value
	 * @param string $name
	 * @param mixed $value
	 */
	public function set($name, $value) {
		$this->{$name} = $value;
	}

	/**
	 * Load bitrix modules or response with error
	 * @param array | string $modules
	 * @param bool $throw
	 * @return bool
	 * @throws \Bitrix\Main\LoaderException
	 */
	private function loadModules($modules, $throw = true) {
		if (!is_array($modules)) {
			$modules = [$modules];
		}

		foreach ($modules as $module) {
			$loaded = Loader::includeModule($module);
			if (!$loaded) {
				if ($throw) {
					throw new InternalServerErrorHttpException(Loc::getMessage('MODULE_NOT_INSTALLED', [
						'#MODULE#' => 'iblock',
					]));
				} else {
					return false;
				}
			}
		}

		return true;
	}

	public function prepareQuery() {
		global $DB;
		$this->context = Application::getInstance()->getContext();
		$this->flightRequest = Flight::request();

		$reflection = new ReflectionObject($this);
		$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach ($properties as $property) {
			$field = $property->getName();

			$value = $this->context->getRequest()->get($field);

			if (!$value) continue;

			if (json_decode($value, true)) {
				$value = json_decode($value, true);
			}

			switch ($field) {
				case 'filter':
					// Convert filter by date to site format
					$dateFields = array_keys(array_filter($this->schema, function($type) {
						return in_array($type, ['date', 'datetime']);
					}));

					foreach ($dateFields as $dateField) {
						$keys = array_filter(array_keys($value), function($key) use ($dateField) {
							return strpos($key, $dateField) !== false;
						});
						foreach ($keys as $key) {
							if ($value[$key]) {
								$value[$key] = date(
									$DB->DateFormatToPHP(CSite::GetDateFormat()),
									strtotime($value[$key])
								);
							}
						}
					}
					break;

				case 'navParams':
					$value = array_merge($this->navParams, $value);
					break;
			}

			$this->{$field} = $value;
		}

		$this->body = $this->flightRequest->data->getData();
	}

	/**
	 * Generate success message
	 * @param mixed $message
	 * @return array success message
	 */
	public function success($message) {
		return [
			'result' => 'ok',
			'message' => $message
		];
	}

	private function checkEntity() {
		if (
			property_exists($this, 'entity') &&
			!is_subclass_of($this->entity, '\Bitrix\Main\Entity\DataManager')
		) {
			throw new Exception('entity property must extends \Bitrix\Main\Entity\DataManager');
		}
	}

	private function setSelectFieldsFromEntityClass() {
		$this->checkEntity();
		if (is_callable([$this->entity, 'getMap'])) {
			$map = call_user_func([$this->entity, 'getMap']);
			$this->select = [];
			foreach ($map as $key => $field) {
				if ($field instanceof \Bitrix\Main\Entity\Field) {
					$key2 = $field->getName() .
						(
						$field instanceof \Bitrix\Main\Entity\ReferenceField ?
							$this->ormNestedSelectSeparator :
							''
						);
					$this->select[$key2] = $field->getName();
				} else {
					$key2 = $key;
					if (isset($field['reference'])) {
						$key2 .= ':';
					}
					$this->select[$key2] = $key;
				}
			}
		}
	}

	private function buildSchema() {
		$this->checkEntity();

		if (!is_callable([$this->entity, 'getMap'])) {
			throw new Exception('Cannot get entity map');
		}

		$map = call_user_func([$this->entity, 'getMap']);
		$schema = [];
		foreach ($map as $key => $field) {
			// Skip for expression field cause getDataType() throws error
			if ($field instanceof \Bitrix\Main\Entity\ExpressionField) {
				continue;
			}

			if ($field instanceof \Bitrix\Main\Entity\Field) {
				$schema[$field->getName()] = $field->getDataType();
			} else {
				$schema[$key] = $field['data_type'];
			}
		}

		$this->set('schema', $schema);
	}

	private function readORM() {
		$this->checkEntity();

		$this->registerOrmNestedFieldsTransform();

		$is_array_assoc = function ($arr) {
			$i = 0;
			foreach ($arr as $k => $val) {
				if('' . $k !== '' . $i) {
					return true;
				}
				$i++;
			}
			return false;
		};

		if (!$is_array_assoc($this->select)) {
			$this->select = array_combine(
				array_values($this->select),
				array_values($this->select)
			);
		}

		// Reset select to asterisk if no any fields passed
		if (count($this->select) === 1 && in_array('*', $this->select)) {
			$this->select = ['*'];
		}

		if ((!isset($this->limit) || !isset($this->offset)) && !empty($this->navParams)) {
			$this->limit = (int) $this->navParams['nPageSize'];
			$this->offset = 0;

			if ($this->navParams['iNumPage']) {
				$this->offset = $this->limit * (int) $this->navParams['iNumPage'];
			}
		}

		$params = [
			'filter' => $this->filter,
			'order' => $this->order,
			'select' => $this->select,
			'limit' => $this->limit,
			'offset' => $this->offset,
		];
		$query = call_user_func_array([$this->entity, 'getList'], [$params]);

		return $query->fetchAll();
	}

	/**
	 * Set public and protected properties from $options arg. Do not touch private props
	 * @param array $options
	 */
	private function setPropertiesFromArray(array $options) {
		$reflection = new ReflectionObject($this);
		$properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
		foreach ($properties as $property) {
			$prop = $property->getName();
			if (!empty($options[$prop])) {
				$this->{$prop} = $options[$prop];
			}
		}
	}

	private function registerBasicTransformHandler() {
		global $SPACEONFIRE_RESTIFY;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$SPACEONFIRE_RESTIFY->getId(),
			'transform',
			[$this, 'basicTransformActions']
		);
	}

	public function basicTransformActions(Event $event) {
		$params = $event->getParameters();
		foreach ($params['result'] as $key => $item) {
			$item = Tools::removeTildaKeys($item);
			$item = self::decodeSpecialChars($item);
			$item = $this->runFormatters($item);
			$params['result'][$key] = $item;
		}
	}

	/**
	 * Decode html entities and special chars in content fields
	 * @param array $item
	 * @return array
	 */
	private static function decodeSpecialChars($item) {
		$contentFields = ['NAME', 'PREVIEW_TEXT', 'DETAIL_TEXT'];
		foreach ($contentFields as $field) {
			if ($item[$field]) {
				$item[$field] = html_entity_decode($item[$field], ENT_QUOTES | ENT_HTML5);
			}
		}
		return $item;
	}

	private function runFormatters($item) {
		foreach ($this->formatters as $formatter => $type) {
			$fields = array_keys(array_filter($this->schema, function ($fieldType) use ($type) {
				return strpos($fieldType, $type) !== false;
			}));

			// Add suffix to properties fields
			$fields = array_map(function ($field) {
				return strpos($field, 'PROPERTY') !== false ? $field . '_VALUE' : $field;
			}, $fields);

			foreach ($fields as $field) {
				if (!empty($item[$field])) {
					if (!is_array($item[$field])) {
						$item[$field] = call_user_func_array([$formatter, 'format'], [$item[$field]]);
					} else {
						$item[$field] = array_map(function ($val) use ($formatter) {
							return call_user_func_array([$formatter, 'format'], [$val]);
						}, $item[$field]);
					}
				}
			}
		}

		return $item;
	}

	private function registerOneItemTransformHandler() {
		global $SPACEONFIRE_RESTIFY;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$SPACEONFIRE_RESTIFY->getId(),
			'transform',
			[$this, 'popOneItemTransformAction'],
			false,
			99999
		);
	}

	public function popOneItemTransformAction(Event $event) {
		$params = $event->getParameters();
		$params['result'] = array_pop($params['result']);
	}

	private function registerOrmNestedFieldsTransform() {
		global $SPACEONFIRE_RESTIFY;
		// Register transform
		EventManager::getInstance()->addEventHandler(
			$SPACEONFIRE_RESTIFY->getId(),
			'transform',
			[$this, 'ormNestedFieldsTransformAction'],
			false,
			88888
		);
	}

	public function ormNestedFieldsTransformAction(Event $event) {
		$params = $event->getParameters();
		foreach ($params['result'] as $key => $item) {
			$params['result'][$key] = $this->recursiveNestedFieldsParse($item, $this->ormNestedSelectSeparator);
		}
	}

	private function recursiveNestedFieldsParse(array $item, $separator = ':') {
		$nestedKeys = array_filter(array_keys($item), function ($key) use ($separator) {
			return strpos($key, $separator) !== false;
		});

		if (!count($nestedKeys)) {
			return $item;
		}

		foreach ($nestedKeys as $key) {
			$prefix = explode($separator, $key);
			$field = array_pop($prefix);
			$prefix = implode($separator, $prefix);
			$item[$prefix][$field] = $item[$key];
			unset($item[$key]);
		}

		return $this->recursiveNestedFieldsParse($item, $separator);
	}
}
