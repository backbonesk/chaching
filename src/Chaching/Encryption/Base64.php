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


class Base64 extends \Chaching\Encryption
{
	public function sign($signature_base)
	{
		return base64_encode(pack('H*', sha1($signature_base)));
	}
}
