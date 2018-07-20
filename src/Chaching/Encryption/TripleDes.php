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


class TripleDes extends \Chaching\Encryption
{
	public function sign($signature_base)
	{
		$hash = sha1($signature_base, TRUE);

		while (strlen($hash) < 24)
		{
			$hash .= chr(0xFF);
		}

		$shared_secret = base64_decode($this->authorization['shared_secret']);
		$key = $shared_secret . substr($shared_secret, 0, 8);

		$iv = chr(0x00);
		$iv .= $iv;
		$iv .= $iv;
		$iv .= $iv;

		return base64_encode(openssl_encrypt(
			$hash,
			'des-ede3-cbc',
			$key,
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
			$iv
		));
	}
}
