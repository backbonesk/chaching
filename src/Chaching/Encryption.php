<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2015 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;

use \Chaching\Exceptions\InvalidAuthorizationException;

abstract class Encryption
{
	protected $authorization = array();

	abstract public function sign($signature_base);

	public function __construct(Array $authorization)
	{
		if (!is_array($authorization) OR count($authorization) < 2)
			throw new InvalidAuthorizationException(
				"Merchant authorization information is missing."
			);

		$this->authorization = $authorization;
	}
}
