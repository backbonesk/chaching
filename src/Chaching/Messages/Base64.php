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

class Base64 extends \Chaching\Message
{
	protected function sign($value)
	{
		return base64_encode(pack('H*', sha1($value)));
	}
}
