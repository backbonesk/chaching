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


class Base64 extends \Chaching\Encryption
{
	public function sign($signature_base, $hash_algorithm = 'sha512')
	{
		return base64_encode(pack('H*', hash($hash_algorithm, $signature_base)));
	}
}
