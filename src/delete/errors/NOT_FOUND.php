<?

namespace goldencode\Bitrix\Restify\Errors;

class NOT_FOUND extends \Exception {
	public function __construct($message = 'Not Found', $code = 404)
	{
		parent::__construct($message, $code);
	}
}
