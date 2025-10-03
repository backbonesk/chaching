<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Encryption;


class PemKeys extends \Chaching\Encryption
{
	public function sign($signature_base)
	{
		$resource_id = openssl_pkey_get_private(
			file_get_contents(
				$this->authorization[ 1 ]['certificate']
			),
			$this->authorization[ 1 ]['passphrase']
		);

		openssl_sign($signature_base, $signature, $resource_id);

		$signature = base64_encode($signature);

		if (PHP_VERSION_ID < 80000) {
			openssl_free_key($resource_id);
		}

		return $signature;
	}

	public function verify($given_signature, $signature_base)
	{
		$resource_id = openssl_pkey_get_public(
			file_get_contents($this->authorization[ 1 ]['key'])
		);

		$given_signature = base64_decode($given_signature);
		$result = openssl_verify($signature_base, $given_signature, $resource_id);

		if (PHP_VERSION_ID < 80000) {
			openssl_free_key($resource_id);
		}

		return (bool) ($result === 1);
	}
}
