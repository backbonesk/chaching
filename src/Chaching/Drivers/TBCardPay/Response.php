<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TBCardPay;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Aes256;
use \Chaching\Encryption\Des;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message implements \Chaching\ECDSAResponseInterface
{
	use \Chaching\ECDSAResponseValidator;

	public $card_id 			= NULL;
	public $transaction_id 		= NULL;
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'VS', 'RES', 'SIGN', 'AC' ];

		if (isset($attributes['TRES']))
		{
			$this->readonly_fields[] = 'TRES';
			$this->readonly_fields[] = 'CID';
		}

		$this->set_authorization($authorization);

		if (isset($this->auth[ 1 ]) AND strlen($this->auth[ 1 ]) === 128)
		{
			array_push(
				$this->readonly_fields,
				'AMT', 'CURR', 'TXN', 'TID', 'ECDSA_KEY', 'ECDSA', 'TIMESTAMP',
				'HMAC'
			);
		}

		foreach ($this->readonly_fields as $field)
		{
			$this->fields[ $field ] = (isset($attributes[ $field ]) AND !empty($attributes[ $field ]))
				? $attributes[ $field ]
				: NULL;
		}

		if (isset($this->fields['HMAC']))
		{
			$this->fields['SIGN'] = $this->fields['HMAC'];

			unset($this->fields['HMAC']);
		}

		if (isset($options['ecdsa_keys_file']) AND is_file($options['ecdsa_keys_file']))
		{
			preg_match_all(
				'/KEY_ID: (\d+)\nSTATUS: ([a-zA-Z0-9]+)\n' .
				'(-----BEGIN PUBLIC KEY.*END PUBLIC KEY-----\n)/isU',
				file_get_contents($options['ecdsa_keys_file']),
				$ecdsa_keys
			);

			foreach ($ecdsa_keys[ 1 ] as $key => $ecdsa_key)
			{
				if ($ecdsa_keys[ 2 ][ $key ] !== 'VALID')
					continue;

				$this->ecdsa_keys[ $ecdsa_key ] = $ecdsa_keys[ 3 ][ $key ];
			}
		}

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidOptionsException
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		$signature = $this->sign();

		if ($this->fields['SIGN'] !== $signature)
			throw new InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIGN']
			));

		if (strlen($this->auth[ 1 ]) === 128)
		{
			list($status, $error_message) = $this->validate_ecdsa_signature();

			if ($status !== TRUE)
				throw new InvalidResponseException($error_message);
		}

		$this->variable_symbol 	= $this->fields['VS'];
		$this->transaction_id 	= $this->fields['TID'];

		if (isset($this->fields['TRES']))
		{
			$this->fields['TRES'] 	= strtolower($this->fields['TRES']);

			$this->card_id 			= $this->fields['CID'];
			$this->status 			= ($this->fields['TRES'] === 'ok' AND !empty($this->fields['VS']) AND !empty($this->fields['CID']))
				? TransactionStatuses::SUCCESS
				: TransactionStatuses::FAILURE;
		}
		else
		{
			$this->fields['RES'] 	= strtolower($this->fields['RES']);

			$this->status 			= ($this->fields['RES'] === 'ok' AND !empty($this->fields['VS']))
				? TransactionStatuses::SUCCESS
				: TransactionStatuses::FAILURE;
		}

		return $this->status;
	}

	protected function sign()
	{
		$field_list = isset($this->fields['TRES'])
			? [ 'VS', 'TRES', 'AC', 'CID' ]
			: [ 'VS', 'RES', 'AC' ];

		switch (strlen($this->auth[ 1 ]))
		{
			case 8:
				$encryption = new Des($this->auth);
				break;

			case 64:
				$encryption = new Aes256($this->auth);
				break;

			case 128:
			default:
				$field_list = [
					'AMT', 'CURR', 'VS', 'TXN', 'RES', 'AC', 'TRES', 'CID',
					'TID', 'TIMESTAMP'
				];

				$encryption = new Hmac($this->auth);
				break;
		}


		$signature_base = '';

		foreach ($field_list as $field)
		{
			$signature_base .= isset($this->fields[ $field ])
				? $this->fields[ $field ]
				: '';
		}

		if ($encryption instanceof Hmac)
			return strtolower($encryption->sign($signature_base));

		return $encryption->sign($signature_base);
	}

	public function ecdsa_signature_base()
	{
		$field_list = [
			'AMT', 'CURR', 'VS', 'TXN', 'RES', 'AC', 'TRES', 'CID',
			'TID', 'TIMESTAMP', 'SIGN'
		];

		$signature_base = '';

		foreach ($field_list as $field)
		{
			$signature_base .= isset($this->fields[ $field ])
				? $this->fields[ $field ]
				: '';
		}

		return $signature_base;
	}
}
