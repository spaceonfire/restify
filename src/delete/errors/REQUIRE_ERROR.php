<?

namespace goldencode\Bitrix\Restify\Errors;

class REQUIRE_ERROR extends \Exception {
	public function __construct($message = 'Unresolved requirements', $code = 500)
	{
		parent::__construct($message, $code);
	}
}
