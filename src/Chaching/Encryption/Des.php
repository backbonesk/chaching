<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
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
		$hash = substr(mhash(MHASH_SHA1, $signature_base), 0, 8);

		if (!function_exists('mcrypt_module_open'))
			throw new MissingDependencyException(
				'mcrypt PHP extension is required'
			);

		$td = mcrypt_module_open(MCRYPT_TRIPLEDES, '', MCRYPT_MODE_ECB, '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

		mcrypt_generic_init($td, $this->authorization[ 1 ], $iv);

		$signature = strtoupper(bin2hex(mcrypt_generic($td, $hash)));

		mcrypt_generic_deinit($td);

		mcrypt_module_close($td);

		return $signature;
	}
}
