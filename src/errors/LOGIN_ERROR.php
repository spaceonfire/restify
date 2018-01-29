<?

namespace goldencode\Bitrix\Restify\Errors;

class LOGIN_ERROR extends \Exception {
	public function __construct($message = 'LOGIN_ERROR', $code = 500)
	{
		parent::__construct($message, $code);
	}
}
