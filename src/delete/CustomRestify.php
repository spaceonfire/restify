<?

namespace goldencode\CosmoPlus\api;

use CIBlockProperty;
use CIBlockElement;
use CSalePaySystem;
use Flight;
use Exception;
use goldencode\Helpers\Bitrix\IblockUtility;
use goldencode\Bitrix\Restify\Core;

class CustomRestify extends Core {
	public $iblock;

	/**
	 * RestifyCatalog constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options = []) {
		parent::__construct();

		$this->requireModules(['catalog', 'sale', 'iblock']);

		try {
			$this->iblock = IblockUtility::getIblockIdByCode('Product');
		} catch (\Exception $exception) {
			$this->error($exception);
		}

		Flight::route('GET /api/v1/Product/filter', [$this, 'productFilter']);
		Flight::route('GET /api/v1/Delivery', [$this, 'getDeliveries']);
		Flight::route('GET /api/v1/PaySystem', [$this, 'getPaySystems']);
	}

	public function productFilter() {
		$PROPS = [];

		$req = $this->request();
		$filter = $req->query->__get('filter');
		$filter['IBLOCK_ID'] = $this->iblock;

		// Get iblock props
		$propsQ = CIBlockProperty::GetList([], ['ACTIVE' => 'Y', 'IBLOCK_ID' => $this->iblock]);
		while ($prop = $propsQ->Fetch()) {
			$prop['VALUES'] = [];
			$PROPS[$prop['CODE']] = $prop;
		}

		// Get available values for props
		$select = array_reduce($PROPS, function($res, $item) {$res[] = 'PROPERTY_' . $item['CODE']; return $res;}, []);
		$elementsQ = CIBlockElement::GetList([], $filter, false, false, $select);
		while ($el = $elementsQ->Fetch())
			foreach (array_keys($PROPS) as $field)
				$PROPS[$field]['VALUES'][] = $el['PROPERTY_' . $field . '_VALUE'];

		// Unique values without false and null
		foreach ($PROPS as $code => $prop) {
			$prop['VALUES'] = array_values(array_filter(array_unique($prop['VALUES'])));

			foreach ($prop['VALUES'] as $i => $value) {
				$value = ['VALUE' => $value];

				switch ($prop['PROPERTY_TYPE']) {
					case 'E':
						$value['NAME'] = CIBlockElement::GetList([], ['ID' => $value['VALUE']], false, false, ['NAME'])->Fetch()['NAME'];
						break;

					default:
						$value['NAME'] = $value['VALUE'];
						break;
				}

				$prop['VALUES'][$i] = $value;
			}
			$PROPS[$code] = $prop;
		}

		$this->json($PROPS);
	}

	/**
	 * @throws \Bitrix\Main\ArgumentException
	 */
	public function getDeliveries() {
		$deliveries = \Bitrix\Sale\Delivery\Services\Manager::getActiveList();

		// filter only global deliveries
		foreach ($deliveries as $i => $delivery) {
			if ($delivery['PARENT_ID'] > 0)
			{
				unset($deliveries[$i]);
				continue;
			}

			$delivery = $this->prepareOutput($delivery);
			$deliveries[$i] = $delivery;
		}

		$deliveries = array_values($deliveries);

		$this->json($deliveries);
	}

	public function getPaySystems() {
		$paySystemsQ = CSalePaySystem::GetList(
			['SORT'=>'ASC', 'NAME'=>'ASC'],
			['ACTIVE' => 'Y'],
			false,
			false,
			['*']
		);
		$paySystems = [];

		while ($paySystem = $paySystemsQ->GetNext()) {
			$paySystem = $this->prepareOutput($paySystem);
			$paySystem['PSA_PARAMS'] = unserialize(html_entity_decode($paySystem['PSA_PARAMS']));
			$paySystems[] = $paySystem;
		}

		$this->json($paySystems);
	}
}
