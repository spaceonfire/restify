<?

namespace goldencode\Bitrix\Restify\Errors;

class FORGOT_PASSWORD_ERROR extends \Exception {
	public function __construct($message = 'Forgot password error', $code = 400)
	{
		parent::__construct($message, $code);
	}
}
