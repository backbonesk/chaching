<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2018 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Encryption;

use \Chaching\Exceptions\MissingDependencyException;


class Aes256 extends \Chaching\Encryption
{
	public function sign($signature_base)
	{
		$hash = substr(mhash(MHASH_SHA1, $signature_base), 0, 16);

		if (!function_exists('mcrypt_module_open'))
			throw new MissingDependencyException(
				'mcrypt PHP extension is required'
			);

		$iv = mcrypt_create_iv(mcrypt_get_iv_size(
			MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB
		), MCRYPT_DEV_URANDOM);

		return strtoupper(bin2hex(mcrypt_encrypt(
			MCRYPT_RIJNDAEL_128,
			pack('H*', $this->authorization[ 1 ]), $hash, MCRYPT_MODE_ECB, $iv
		)));
	}
}
