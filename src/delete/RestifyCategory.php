<?

namespace goldencode\Bitrix\Restify;

use CIBlockSection;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\CREATE_ERROR;
use goldencode\Bitrix\Restify\Errors\DELETE_ERROR;
use goldencode\Bitrix\Restify\Errors\NOT_FOUND;
use goldencode\Bitrix\Restify\Errors\UPDATE_ERROR;
use goldencode\Helpers\Bitrix\IblockUtility;

class RestifyCategory extends Core {
	public $iblock;

	/**
	 * RestifyCategory constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options) {
		parent::__construct();
		$this->requireModules(['iblock']);

		$this->options['defaults select'] = array_merge(
			$this->options['defaults select'],
			[
				'PICTURE'
			]
		);

		$this->options = array_merge($this->options, $options);

		$this->formatters = array_merge($this->formatters, $this->options['formatters'] ?? []);

		try {
			$this->iblock = IblockUtility::getIblockIdByCode($options['iblock']);
		} catch (\Exception $exception) {
			$this->error($exception);
		}

		$manyUri =
			$this->options['prefix'] .
			$this->options['version'] .
			'/' . $this->options['iblock'] . 'Category';

		$oneUri = $manyUri . '/@id';

		Flight::route("GET $manyUri", [$this, 'readMany']);
		Flight::route("POST $manyUri", [$this, 'create']);
		Flight::route("GET $manyUri/count", [$this, 'count']);

		Flight::route("GET $oneUri", [$this, 'readOne']);
		Flight::route("POST $oneUri", [$this, 'update']);
		Flight::route("DELETE $oneUri", [$this, 'delete']);
	}

	/**
	 * Get Category(ies) of iblock
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

		$query = CIBlockSection::GetList($order, $filter, false, $select, $navParams);

		$results = [];
		while ($item = $query->GetNext()) {
			$item = $this->prepareOutput($item);
			$results[] = $item;
		}

		if (!count($results) && $id)
			throw new NOT_FOUND('Category with this id or code not found');

		$this->json($id ? array_pop($results) : $results);
	}

	/**
	 * Get category by id or code
	 * @param int|string $id
	 * @throws Exception
	 */
	public function readOne($id) {
		// disable filter
		$this->options['defaults filter'] = ['ACTIVE' => 'Y'];
		$this->readMany($id);
	}

	/**
	 * Get categories count in iblock
	 * @throws Exception
	 */
	public function count() {
		$req = $this->request();
		$order = $req->query->__get('order');
		$filter = $req->query->__get('filter');

		$filter['IBLOCK_ID'] = $this->iblock;

		$query = CIBlockSection::GetList($order, $filter, false, ['ID']);

		$results = [];
		while ($item = $query->GetNext()) {
			$item = $this->prepareOutput($item);
			$results[] = $item;
		}

		$this->json(['count' => count($results)]);
	}


	/**
	 * Create category
	 * @throws CREATE_ERROR
	 * @throws Exception
	 */
	public function create() {
		$req = $this->request();
		$data = $req->data->getData();
		$data['IBLOCK_ID'] = $this->iblock;
		$section = new CIBlockSection;
		$sectionId = $section->Add($data);
		if (!$sectionId) throw new CREATE_ERROR($section->LAST_ERROR);

		$this->readOne($sectionId);
	}

	/**
	 * Update category by ID
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

		$section = new CIBlockSection();
		if (!$section->Update($id, $data))
			throw new UPDATE_ERROR($section->LAST_ERROR);

		$this->readOne($id);
	}

	/**
	 * Delete category by ID
	 * @param $id
	 * @throws Exception
	 */
	public function delete($id) {
		global $DB;
		$DB->StartTransaction();

		try {
			$result = CIBlockSection::Delete($id);
			if (!$result) throw new DELETE_ERROR("Cannot delete section with id=$id");
		} catch (Exception $exception) {
			$DB->Rollback();
			throw $exception;
		}

		$DB->Commit();

		$this->success('Section successfully deleted');
	}

	public function prepareOutput(array $item){
		$item = parent::prepareOutput($item);
		$item['ELEMENTS_COUNT'] = (int) (new CIBlockSection())->GetSectionElementsCount($item['ID'], ['CNT_ACTIVE'=>'Y']);
		return $item;
	}
}
