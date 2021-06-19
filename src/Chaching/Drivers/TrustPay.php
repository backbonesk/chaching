<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers;


class TrustPay extends \Chaching\Driver
{
	public function request(Array $attributes)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\TrustPay\Request(
				$this->authorization, $attributes, $this->options
			);
		}

		return $request;
	}

	public function response(Array $attributes)
	{
		static $response = NULL;

		if ($response === NULL)
		{
			$response = new \Chaching\Drivers\TrustPay\Response(
				$this->authorization, $attributes, $this->options
			);
		}

		return $response;
	}

	public function notification(Array $attributes)
	{
		static $notification = NULL;

		if ($notification === NULL)
		{
			$notification = new \Chaching\Drivers\TrustPay\Notification(
				$this->authorization, $attributes, $this->options
			);
		}

		return $notification;
	}
}
