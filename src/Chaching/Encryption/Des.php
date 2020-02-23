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

use \Chaching\Exceptions\MissingDependencyException;


class Des extends \Chaching\Encryption
{
	public function sign($signature_base)
	{
		return strtoupper(bin2hex(openssl_encrypt(
			substr(openssl_digest($signature_base, 'sha1', TRUE), 0, 8),
			'des-ecb',
			$this->authorization[ 1 ],
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
		)));
	}
}
