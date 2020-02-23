<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2019 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Encryption;


class Hmac extends \Chaching\Encryption
{
	public function sign($signature_base)
	{
		$shared_secret = (strlen($this->authorization[ 1 ]) === 128)
			? pack('H*', $this->authorization[ 1 ])
			: pack('A*', $this->authorization[ 1 ]);

		return strtoupper(hash_hmac(
			'sha256',
			pack('A*', $signature_base),
			$shared_secret,
			FALSE
		));
	}
}
