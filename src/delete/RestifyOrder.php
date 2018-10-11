<?

namespace goldencode\Bitrix\Restify;

use CIBlockElement;
use CPrice;
use CSaleBasket;
use CSaleDelivery;
use CSaleOrder;
use CSaleOrderProps;
use CSaleOrderPropsValue;
use CSalePersonType;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\BAD_REQUEST;
use goldencode\Bitrix\Restify\Errors\CREATE_ERROR;
use goldencode\Bitrix\Restify\Errors\DELETE_ERROR;
use goldencode\Bitrix\Restify\Errors\NOT_AUTH;
use goldencode\Bitrix\Restify\Errors\UPDATE_ERROR;

class RestifyOrder extends Core {
	public $iblock;

	/**
	 * RestifyCart constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options = []) {
		parent::__construct();
		$this->requireModules(['sale']);

		$this->options['defaults order'] = ['DATE_INSERT' => 'DESC'];

		$this->options['defaults filter'] = [
			'LID' => SITE_ID
		];

		$this->options['defaults select'] = [
			'ID',
			'LID',
			'ACCOUNT_NUMBER',
			'TRACKING_NUMBER',
			'PAY_SYSTEM_ID',
			'DELIVERY_ID',
			'DATE_INSERT',
			'DATE_UPDATE',
			'PERSON_TYPE_ID',
			'USER_ID',
			'PAYED',
			'DATE_PAYED',
			'EMP_PAYED_ID',
			'STATUS_ID',
			'DATE_STATUS',
			'PRICE_DELIVERY',
			'ALLOW_DELIVERY',
			'DATE_ALLOW_DELIVERY',
			'RESERVED',
			'PRICE',
			'CURRENCY',
			'DISCOUNT_VALUE',
			'TAX_VALUE',
			'SUM_PAID',
			'USER_DESCRIPTION',
			'ADDITIONAL_INFO',
			'COMMENTS',
			'RESPONSIBLE_ID',
			'DATE_PAY_BEFORE',
			'DATE_BILL',
			'CANCELED',
			'DATE_CANCELED',
			'REASON_CANCELED',
			'RUNNING'
		];
		$this->options = array_merge($this->options, $options);

		$this->formatters = array_merge($this->formatters, $this->options['formatters'] ?? []);

		$manyUri =
			$this->options['prefix'] .
			$this->options['version'] .
			'/Order';

		$oneUri = $manyUri . '/@id';

		Flight::route("GET $manyUri", [$this, 'read']);
		Flight::route("POST $manyUri", [$this, 'create']);
//		Flight::route("POST $oneUri", [$this, 'update']);
//		Flight::route("DELETE $oneUri", [$this, 'delete']);
	}

	/**
	 * Get orders
	 * @throws Exception
	 */
	public function read() {
		global $USER;
		$this->requireUser();

		$req = $this->request();
		$order = $req->query->__get('order');
		$filter = $req->query->__get('filter');
		$navParams = $req->query->__get('navParams');
		$select = $req->query->__get('select');

		$filter['USER_ID'] = $USER->GetID();

		$query = CSaleOrder::GetList($order, $filter, false, $navParams, $select);

		$results = [];
		while ($item = $query->GetNext()) {
			$item = $this->prepareOutput($item);
			$results[] = $item;
		}

		$this->json($results);
	}

	/**
	 * Create order
	 * @throws NOT_AUTH user not authorized
	 * @throws BAD_REQUEST throw when cart is empty, delivery not defined, pay system not defined
	 * @throws Exception other
	 */
	public function create() {
		global $USER, $APPLICATION;
		$this->requireUser();

		$req = $this->request();
		$data = $req->data->getData();
		$basket = new CSaleBasket();
		$order = new CSaleOrder();
		$currentUser = $USER->GetID();

		if (!$data['DELIVERY']) throw new BAD_REQUEST('Не указан способ доставки');
		if (!$data['PAY_SYSTEM']) throw new BAD_REQUEST('Не указан способ оплаты');

		// Count order price
		$currentCart = $basket->GetBasketUserID();

		$dbBasketItems = $basket->GetList([], [
			'FUSER_ID' => $currentCart,
			'LID' => SITE_ID,
			'ORDER_ID' => 'NULL'
		]);
		$ORDER_PRICE = 0;
		while ($item = $dbBasketItems->GetNext())
			$ORDER_PRICE += (float) $item['PRICE'] * $item['QUANTITY'];

		if ($ORDER_PRICE === 0) throw new BAD_REQUEST('Корзина пуста!');

		// Delivery
		$delivery = \Bitrix\Sale\Delivery\Services\Manager::getById((int) $data['DELIVERY']);
		$deliveryPrice = $delivery['CONFIG']['MAIN']['PRICE'];

		if (!empty($delivery['CONFIG']['MAIN']['MARGIN_TYPE']))
			$deliveryPrice =
				$delivery['CONFIG']['MAIN']['MARGIN_TYPE'] === 'CURRENCY' ?
					$delivery['CONFIG']['MAIN']['MARGIN_VALUE'] :
					$ORDER_PRICE * (int) $delivery['CONFIG']['MAIN']['MARGIN_VALUE'] / 100;

		// Create order
		$ORDER_ID = $order->Add([
			'LID' => SITE_ID,
			'PERSON_TYPE_ID' => (new CSalePersonType())->GetList()->Fetch()['ID'],
			'PAYED' => 'N',
			'CANCELED' => 'N',
			'STATUS_ID' => 'N',
			'PRICE' => $ORDER_PRICE,
			'PRICE_DELIVERY' => $deliveryPrice,
			'ALLOW_DELIVERY' => 'Y',
			'CURRENCY' => 'RUB',
			'USER_ID' => $currentUser,
			'PAY_SYSTEM_ID' => $data['PAY_SYSTEM'],
			'DELIVERY_ID' => $delivery['ID']
		]);

		if (!$ORDER_ID)
			throw new CREATE_ERROR(
				$APPLICATION->LAST_ERROR ?
					$APPLICATION->LAST_ERROR :
					'Не удалось создать заказ'
			);

		$basket->OrderBasket($ORDER_ID, $currentCart);

		// Add props
		$orderPropsQ = (new CSaleOrderProps())->GetList();
		while ($prop = $orderPropsQ->GetNext())
			if (isset($data[$prop['CODE']]))
				(new CSaleOrderPropsValue())->Add([
					'ORDER_ID' => $ORDER_ID,
					'ORDER_PROPS_ID' => $prop['ID'],
					'NAME' => $prop['NAME'],
					'CODE' => $prop['CODE'],
					'VALUE' => $data[$prop['CODE']]
				]);

		$this->success('Заказ "№' . $ORDER_ID . '" успешно создан');
	}

	/**
	 * Throw error if user unauthorized
	 * @throws NOT_AUTH
	 */
	private function requireUser() {
		global $USER;
		if (!$USER->GetID())
			throw new NOT_AUTH('Auth required');
	}

	public function prepareOutput(array $order)
	{
		$order = parent::prepareOutput($order);

		$order['ITEMS'] = [];
		$itemsFilter = ['ORDER_ID' => $order['ID']];
		$itemsQuery = (new CSaleBasket())->GetList(['NAME' => 'ASC'], $itemsFilter, false, [], ['ID', 'PRODUCT_ID', 'QUANTITY']);
		while ($item = $itemsQuery->GetNext()) {
			$product = CIBlockElement::GetList([], ['ID' => $item['PRODUCT_ID']], false, false, [
				'ID',
				'NAME',
				'CODE',
				'SORT',
				'IBLOCK_SECTION_ID',
				'PREVIEW_PICTURE',
				'PREVIEW_TEXT',
				'DETAIL_PICTURE',
				'DETAIL_TEXT',
				'PROPERTY_BRAND',
				'PROPERTY_COLOR',
				'PROPERTY_TYPE'
			])->Fetch();
			$product['BASE_PRICE'] = CPrice::GetBasePrice($product['ID']);
			$product = $this->populate($product, [
				'BRAND' => [
					'ID',
					'NAME',
					'CODE',
					'SORT',
					'PREVIEW_PICTURE',
					'PREVIEW_TEXT',
					'DETAIL_PICTURE',
					'DETAIL_TEXT'
				],
				'COLOR' => [
					'ID',
					'NAME',
					'CODE',
					'SORT',
					'PREVIEW_PICTURE',
					'PREVIEW_TEXT',
					'DETAIL_PICTURE',
					'DETAIL_TEXT'
				],
				'TYPE' => [
					'ID',
					'NAME',
					'CODE',
					'SORT',
					'PREVIEW_PICTURE',
					'PREVIEW_TEXT',
					'DETAIL_PICTURE',
					'DETAIL_TEXT'
				],
			]);
			$item['PRODUCT'] = $product;
			$order['ITEMS'][] = parent::prepareOutput($item);
		}

		return $order;
	}

	private function populate(array $item, array $fields) {
		foreach ($fields as $populateField => $selectFields) {
			if (!$item['PROPERTY_' . $populateField . '_VALUE']) continue;

			$populatedItem = CIBlockElement::GetList(
				[],
				['ID' => $item['PROPERTY_' . $populateField . '_VALUE']],
				false,
				false,
				$selectFields
			)->Fetch();

			$populatedItem = parent::prepareOutput($populatedItem);

			$item[$populateField] = $populatedItem;
		}
		return $item;
	}
}
