<?php
namespace Chaching\Messages;

class Hmac extends \Chaching\Message
{
	protected function sign($value)
	{
		$signature_base = pack('A*', $this->signature_base());

		if (strlen($this->auth[ 1 ]) !== 64)
			return NULL;

		$shared_secret = pack('A*', $this->auth[ 1 ]);

		return strtoupper(hash_hmac(
			'sha256', $signature_base, $shared_secret, FALSE
		));
	}
}
