<?

namespace goldencode\Bitrix\Restify\Errors;

class NOT_AUTH extends \Exception {
	public function __construct($message = 'Unauthorized', $code = 401)
	{
		parent::__construct($message, $code);
	}
}
