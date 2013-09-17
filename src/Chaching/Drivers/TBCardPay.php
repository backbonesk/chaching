<?php
namespace Chaching\Drivers;

class TBCardPay extends \Chaching\Driver
{
	public function request(Array $options)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\TBCardPay\Request(
				$this->authorization, $options
			);
		}

		return $request;
	}

	public function response(Array $options)
	{
		static $response = NULL;

		if ($response === NULL)
		{
			$response = new \Chaching\Drivers\TBCardPay\Response(
				$this->authorization, $options
			);
		}

		return $response;
	}
}
