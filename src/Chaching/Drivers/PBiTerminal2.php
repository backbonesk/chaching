<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2020 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers;


class PBiTerminal2 extends \Chaching\Driver
{
	public function request(Array $attributes)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\PBiTerminal2\Request(
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
			$response = new \Chaching\Drivers\PBiTerminal2\Response(
				$this->authorization, $attributes, $this->options
			);
		}

		return $response;
	}

	public function refund(Array $attributes)
	{
		static $refund = NULL;

		if ($refund === NULL)
		{
			$refund = new \Chaching\Drivers\PBiTerminal2\Refund(
				$this->authorization, $attributes, $this->options
			);
		}

		return $refund;
	}
}
