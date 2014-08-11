<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Messages;

class Hmac extends \Chaching\Message
{
	protected function sign($value)
	{
		$signature_base = pack('A*', $this->signature_base());

		$shared_secret = (strlen($this->auth[ 1 ]) === 128)
			? pack('A*', pack('H*', $this->auth[ 1 ]))
			: pack('A*', $this->auth[ 1 ]);

		return strtoupper(hash_hmac(
			'sha256', $signature_base, $shared_secret, FALSE
		));
	}
}
