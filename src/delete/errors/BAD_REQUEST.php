<?

namespace goldencode\Bitrix\Restify\Errors;

class BAD_REQUEST extends \Exception {
	public function __construct($message = 'Forbidden', $code = 400)
	{
		parent::__construct($message, $code);
	}
}
