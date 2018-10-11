<?

namespace goldencode\Bitrix\Restify;

use CPrice;
use Exception;

class RestifyCatalog extends Restify {
	/**
	 * RestifyCatalog constructor.
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(array $options) {
		parent::__construct($options);
		$this->requireModules(['catalog']);
	}

	/**
	 * Prepare Output
	 * @param array $item
	 * @param boolean $populate
	 * @return array
	 */
	public function prepareOutput(array $item, $populate = true) {
		$item = parent::prepareOutput($item, $populate);
		$item['BASE_PRICE'] = CPrice::GetBasePrice($item['ID']);
		$item['CAN_BUY'] =
			$item['BASE_PRICE']['PRICE'] &&
			(
				$item['BASE_PRICE']['PRODUCT_CAN_BUY_ZERO'] === 'Y' ||
				$item['BASE_PRICE']['PRODUCT_NEGATIVE_AMOUNT_TRACE'] === 'Y' ||
				(int) $item['BASE_PRICE']['PRODUCT_QUANTITY'] > 0
			);

		if ($this->options['priceId'])
			$item['PRICE'] = CPrice::GetList([], [
				'PRODUCT_ID' => $item['ID'],
				'CATALOG_GROUP_ID' => $this->options['priceId']
			])->Fetch();

		if (!$item['BASE_PRICE'] && !$item['PRICE']) {
			unset($item['BASE_PRICE']);
			unset($item['PRICE']);
			unset($item['CAN_BUY']);
		}

		return $item;
	}
}
