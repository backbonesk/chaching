<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2017 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers;


class SLSPSporoPay extends \Chaching\Driver
{
	public function __construct(Array $authorization, Array $options = [])
	{
		if (!isset($authorization[ 0 ]) OR !preg_match('/^(?<prefix>[0-9]{1,6})?-?(?<account_no>[0-9]{10})\/(?<bank_code>[0-9]{4})$/', $authorization[ 0 ], $match))
			return;

		$this->authorization 	= [
			'prefix' 			=> isset($match['prefix'])
				? $match['prefix']
				: '000000',
			'account_no'		=> $match['account_no'],
			'bank_code' 		=> $match['bank_code'],
			'shared_secret' 	=> isset($authorization[ 1 ])
				? $authorization[ 1 ]
					: ''
		];

		$this->options 			= $options;
	}

	public function request(Array $attributes)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\SLSPSporoPay\Request(
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
			$response = new \Chaching\Drivers\SLSPSporoPay\Response(
				$this->authorization, $attributes, $this->options
			);
		}

		return $response;
	}
}
