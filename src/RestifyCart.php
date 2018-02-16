<?

namespace goldencode\Bitrix\Restify;

use CIBlockElement;
use CPrice;
use CSaleBasket;
use Exception;
use Flight;
use goldencode\Bitrix\Restify\Errors\DELETE_ERROR;
use goldencode\Bitrix\Restify\Errors\UPDATE_ERROR;

class RestifyCart extends Core {
	public $iblock;

	/**
	 * RestifyCart constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options = []) {
		parent::__construct();
		$this->requireModules(['sale']);

		$this->options['defaults order'] = ['ID' => 'ASC'];

		$fuserId = (int) CSaleBasket::GetBasketUserID(true);
		$this->options['defaults filter'] = [
			'FUSER_ID' => $fuserId,
			'LID' => SITE_ID,
			'ORDER_ID' => 'NULL'
		];

		$this->options['defaults select'] = [
			'ID',
			'NAME',
			'CALLBACK_FUNC',
			'MODULE',
			'PRODUCT_ID',
			'QUANTITY',
			'DELAY',
			'CAN_BUY',
			'PRICE',
			'WEIGHT',
			'DETAIL_PAGE_URL',
			'NOTES',
			'CURRENCY',
			'VAT_RATE',
			'CATALOG_XML_ID',
			'PRODUCT_XML_ID',
			'SUBSCRIBE',
			'DISCOUNT_PRICE',
			'PRODUCT_PROVIDER_CLASS',
			'TYPE',
			'SET_PARENT_ID',
			'PRODUCT_PRICE_ID',
			'CUSTOM_PRICE',
			'BASE_PRICE'
		];
		$this->options = array_merge($this->options, $options);

		$this->formatters = array_merge($this->formatters, $this->options['formatters'] ?? []);

		$manyUri =
			$this->options['prefix'] .
			$this->options['version'] .
			'/Cart';

		$oneUri = $manyUri . '/@id';

		Flight::route("GET $manyUri", [$this, 'read']);
		Flight::route("POST $manyUri", [$this, 'update']);
		Flight::route("POST $oneUri", [$this, 'update']);
		Flight::route("DELETE $oneUri", [$this, 'delete']);
	}

	/**
	 * Get cart items
	 * @throws Exception
	 */
	public function read() {
		$req = $this->request();
		$order = $req->query->__get('order');
		$filter = $req->query->__get('filter');
		$navParams = $req->query->__get('navParams');
		$select = $req->query->__get('select');

		$query = (new CSaleBasket())->GetList($order, $filter, false, $navParams, $select);

		$results = [];
		while ($item = $query->GetNext()) {
			$item = $this->prepareOutput($item);
			$results[] = $item;
		}

		$this->json($results);
	}

	/**
	 * Update or add product to cart by ID
	 * ID may be passed in url or as body param
	 * @param int|null $productId
	 * @throws Exception
	 * @throws UPDATE_ERROR
	 */
	public function update($productId = null) {
		$req = $this->request();
		$data = $req->data->getData();

		if (!$productId) $productId = $data['ID'];
		unset($data['ID']);
		if (!$productId) throw new UPDATE_ERROR("Id not provided", 400);

		// update cart
		$quantity = (int) $data['QUANTITY'];
		if (!$quantity) $quantity = 1;

		// find item in cart
		$action = 'add';
		$fuserId = (int) CSaleBasket::GetBasketUserID(true);
		$arItem = (new CSaleBasket())->GetList([], [
			'FUSER_ID' => $fuserId,
			'LID' => SITE_ID,
			'ORDER_ID' => 'NULL',
			'PRODUCT_ID' => $productId
		], false, false, ['ID'])->Fetch();
		if ($arItem) $action = 'update';

		switch ($action) {
			case 'update':
				$result = (new CSaleBasket())->Update($arItem['ID'], ['QUANTITY' => $quantity]);
				$successMessage = 'Товар в корзине обновлен';
				break;

			case 'add':
			default:
				$result = Add2BasketByProductID($productId, $quantity);
				$successMessage = 'Товар добавлен в корзину';
				break;
		}

		if (!$result)
			throw new UPDATE_ERROR("Can't add product with id: $productId to cart");

		$this->success($successMessage);
	}

	/**
	 * Delete item from cart by product ID
	 * @param int $productId
	 * @throws Exception
	 */
	public function delete($productId) {
		/** @global \CMain $APPLICATION */
		global $APPLICATION;

		// find item in cart
		$fuserId = (int) CSaleBasket::GetBasketUserID(true);
		$arItem = (new CSaleBasket())->GetList([], [
			'FUSER_ID' => $fuserId,
			'LID' => SITE_ID,
			'ORDER_ID' => 'NULL',
			'PRODUCT_ID' => $productId
		], false, false, ['ID'])->Fetch();

		$result = true;
		if (isset($arItem))
			$result = (new CSaleBasket())->Delete($arItem['ID']);

		if (!$result)
			throw new DELETE_ERROR($APPLICATION->LAST_ERROR);

		$this->success('Товар удален из корзины');
	}

	public function prepareOutput(array $item)
	{
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
		$product = parent::prepareOutput($product);
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
		unset($product);

		$item = parent::prepareOutput($item);
		return $item;
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
