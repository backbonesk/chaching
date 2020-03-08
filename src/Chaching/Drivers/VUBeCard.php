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


class VUBeCard extends \Chaching\Driver
{
	const HASH_ALGORITHM_VERSION_2 = 'ver2';

	public function request(Array $attributes)
	{
		static $request = NULL;

		if ($request === NULL)
		{
			$request = new \Chaching\Drivers\VUBeCard\Request(
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
			$response = new \Chaching\Drivers\VUBeCard\Response(
				$this->authorization, $attributes, $this->options
			);
		}

		return $response;
	}
}
