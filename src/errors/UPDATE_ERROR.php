<?

namespace goldencode\Bitrix\Restify\Errors;

class UPDATE_ERROR extends \Exception {
	public function __construct($message = 'UPDATE_ERROR', $code = 500)
	{
		parent::__construct($message, $code);
	}
}
