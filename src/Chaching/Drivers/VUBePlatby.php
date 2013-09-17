<?php
namespace Chaching\Drivers;

class VUBePlatby extends \Chaching\Driver
{
	public function request(Array $options)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\VUBePlatby\Request(
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
			$response = new \Chaching\Drivers\VUBePlatby\Response(
				$this->authorization, $options
			);
		}

		return $response;
	}
}
