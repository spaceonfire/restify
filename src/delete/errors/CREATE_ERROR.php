<?

namespace goldencode\Bitrix\Restify\Errors;

class CREATE_ERROR extends \Exception {
	public function __construct($message = 'Invalid data', $code = 400)
	{
		parent::__construct($message, $code);
	}
}
