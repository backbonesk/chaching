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

class TripleDes extends \Chaching\Message
{
	protected function sign($value)
	{
		$hash = sha1($value, TRUE);

		while (strlen($hash) < 24)
		{
			$hash .= chr(0xFF);
		}

		$shared_secret 	= base64_decode($this->auth['shared_secret']);
		$key 			= $shared_secret . substr($shared_secret, 0, 8);

		$iv = chr(0x00);
		$iv .= $iv;
		$iv .= $iv;
		$iv .= $iv;

		return base64_encode(mcrypt_encrypt(
			MCRYPT_TRIPLEDES, $key, $hash, MCRYPT_MODE_CBC, $iv
		));
	}
}
