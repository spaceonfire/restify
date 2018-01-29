<?

namespace goldencode\Bitrix\Restify;

use CIBlockElement;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\CREATE_ERROR;
use goldencode\Bitrix\Restify\Errors\DELETE_ERROR;
use goldencode\Bitrix\Restify\Errors\NOT_FOUND;
use goldencode\Bitrix\Restify\Errors\UPDATE_ERROR;
use goldencode\Helpers\Bitrix\IblockUtility;

class Restify extends Core {
	public $iblock;

	/**
	 * Restify constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options) {
		parent::__construct();

		$this->requireModules(['iblock']);

		$this->options['defaults select'] = array_merge(
			$this->options['defaults select'],
			[
				'SORT',
				'PREVIEW_PICTURE',
				'PREVIEW_TEXT',
				'DETAIL_PICTURE',
				'DETAIL_TEXT'
			]
		);
		$this->options = array_merge($this->options, $options);

		try {
			$this->iblock = IblockUtility::getIblockIdByCode($options['iblock']);
		} catch (\Exception $exception) {
			$this->error($exception);
		}

		$manyUri =
			$this->options['prefix'] .
			$this->options['version'] .
			'/' . $this->options['iblock'];

		$oneUri = $manyUri . '/@id';

		Flight::route("GET $manyUri", [$this, 'readMany']);
		Flight::route("POST $manyUri", [$this, 'create']);

		Flight::route("GET $oneUri", [$this, 'readOne']);
		Flight::route("POST $oneUri", [$this, 'update']);
		Flight::route("DELETE $oneUri", [$this, 'delete']);
	}

	/**
	 * Get Item(s) of iblock
	 * @param string|int|null $id Item id or code
	 * @throws Exception
	 */
	public function readMany($id = null) {
		$req = $this->request();
		$order = $req->query->__get('order');
		$filter = $req->query->__get('filter');
		$navParams = $req->query->__get('navParams');
		$select = $req->query->__get('select');

		$filter['IBLOCK_ID'] = $this->iblock;

		if ($id) {
			// ID or CODE
			if (is_numeric($id))
				$filter['ID'] = $id;
			else
				$filter['CODE'] = $id;

			// get only one item
			$navParams = ['nPageSize' => 1];
		}

		$query = CIBlockElement::GetList($order, $filter, false, $navParams, $select);

		$results = [];
		while ($item = $query->GetNext()) {
			$item = $this->prepareOutput($item);
			$results[] = $item;
		}

		if (!count($results) && $id)
			throw new NOT_FOUND('Element with this id or code not found');

		$this->json($id ? array_pop($results) : $results);
	}

	/**
	 * Get item by id or code
	 * @param int|string $id
	 * @throws Exception
	 */
	public function readOne($id) {
		$this->readMany($id);
	}

	/**
	 * Create item
	 * @throws CREATE_ERROR
	 * @throws Exception
	 */
	public function create() {
		$req = $this->request();
		$data = $req->data->getData();
		$data['IBLOCK_ID'] = $this->iblock;
		$el = new CIBlockElement;
		$elId = $el->Add($data);
		if (!$elId) throw new CREATE_ERROR($el->LAST_ERROR);

		$this->readOne($elId);
	}

	/**
	 * Update item by ID
	 * ID may be passed in url or as body param
	 * @param int|null $id
	 * @throws Exception
	 * @throws UPDATE_ERROR
	 */
	public function update($id = null) {
		$this->requireModules(['iblock']);
		$req = $this->request();
		$data = $req->data->getData();
		$data['IBLOCK_ID'] = $this->iblock;

		if (!$id) $id = $data['ID'];
		unset($data['ID']);
		if (!$id) throw new UPDATE_ERROR("Id not provided", 400);

		$el = new CIBlockElement;
		if (!$el->Update($id, $data)) throw new UPDATE_ERROR($el->LAST_ERROR);

		$this->readOne($id);
	}

	/**
	 * Delete item by ID
	 * @param $id
	 * @throws Exception
	 */
	public function delete($id) {
		global $DB;
		$DB->StartTransaction();

		try {
			$result = CIBlockElement::Delete($id);
			if (!$result) throw new DELETE_ERROR("Cannot delete element with id=$id");
		} catch (Exception $exception) {
			$DB->Rollback();
			throw $exception;
		}

		$DB->Commit();

		$this->success('Element successfully deleted');
	}

	/**
	 * Prepare Output
	 * @param array $item
	 * @param boolean $populate
	 * @return array
	 */
	public function prepareOutput(array $item, $populate = true)
	{
		$item = parent::prepareOutput($item);

		if (!empty($this->options['populate']) && $populate) {
			if (!is_array($this->options['populate']))
				$this->options['populate'] = [$this->options['populate']];

			foreach ($this->options['populate'] as $populateField => $selectFields) {
				if (!$item['PROPERTY_' . $populateField . '_VALUE']) continue;

				$populatedItem = CIBlockElement::GetList(
					[],
					['ID' => $item['PROPERTY_' . $populateField . '_VALUE']],
					false,
					false,
					$selectFields
				)->Fetch();

				$populatedItem = $this->prepareOutput($populatedItem, false);

				$item[$populateField] = $populatedItem;
			}
		}

		return $item;
	}
}
