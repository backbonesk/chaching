<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2015 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers;

class PayPal extends \Chaching\Driver
{
	public function request(Array $options)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\PayPal\Request(
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
			$response = new \Chaching\Drivers\PayPal\Response(
				$this->authorization, $options
			);
		}

		return $response;
	}

	public function notification(Array $options)
	{
		static $notification = NULL;

		if ($notification === NULL)
		{
			$notification = new \Chaching\Drivers\PayPal\Notification(
				$this->authorization, $options
			);
		}

		return $notification;
	}
}
