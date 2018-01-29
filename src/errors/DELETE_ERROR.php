<?

namespace goldencode\Bitrix\Restify\Errors;

class DELETE_ERROR extends \Exception {
	public function __construct($message = 'DELETE_ERROR', $code = 500)
	{
		parent::__construct($message, $code);
	}
}
