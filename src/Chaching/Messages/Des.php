<?php
namespace Chaching\Messages;

class Des extends \Chaching\Message
{
	protected function sign($value)
	{
		$hash = substr(mhash(MHASH_SHA1, $value), 0, 8);

		if (function_exists('mcrypt_module_open'))
		{
			$td = mcrypt_module_open(MCRYPT_TRIPLEDES, '', MCRYPT_MODE_ECB, '');
			$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

			mcrypt_generic_init($td, $this->auth[ 1 ], $iv);

			$signature = strtoupper(bin2hex(mcrypt_generic($td, $hash)));

			mcrypt_generic_end($td);
		}
		else
		{
			$signature = strtoupper(bin2hex(mcrypt_ecb(
				MCRYPT_3DES, $this->auth[ 1 ], $hash, MCRYPT_ENCRYPT
			)));
		}

		return $signature;
	}
}
