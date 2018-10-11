<?

namespace goldencode\Bitrix\Restify\Errors;

class RESET_PASSWORD_ERROR extends \Exception {
	public function __construct($message = 'Reset password error', $code = 400)
	{
		parent::__construct($message, $code);
	}
}
