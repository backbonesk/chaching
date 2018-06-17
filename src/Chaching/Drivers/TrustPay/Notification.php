<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2018 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TrustPay;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;


class Notification extends \Chaching\Message
{
	public $status 				= FALSE;
	public $reference_number 	= NULL;

	const PAYMENT_SUCCESS 		= 1;
	const PAYMENT_ANNOUNCED 	= 2;
	const PAYMENT_AUTHORIZED 	= 3;
	const PAYMENT_PROCESSING 	= 4;

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [
			'AID', 'TYP', 'AMT', 'CUR', 'REF', 'RES', 'TID', 'OID', 'TSS',
			'SIG',
			'CardId', 'CardMask', 'CardExp', 'AuthNumber', 'CardRecTxSec',
			'CardAcquirerResponseId', 'SIG2'
		];

		foreach ($this->readonly_fields as $field_name)
		{
			$this->fields[ $field_name ] = isset($attributes[ $field_name ])
				? $attributes[ $field_name ]
				: NULL;
		}

		$this->set_authorization($authorization);

		if (!empty($options))
		{
			$this->set_options($options);
		}

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		$signature = $this->sign();

		if ($this->fields['SIG2'] !== $signature)
			throw new InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIG2']
			));

		$this->reference_number = $this->fields['REF'];

		$correct_statuses = [
			self::PAYMENT_SUCCESS, self::PAYMENT_AUTHORIZED,
			self::PAYMENT_PROCESSING
		];

		if (in_array($this->fields['RES'], $correct_statuses))
		{
			$this->status = TransactionStatuses::SUCCESS;
		}
		else if ($this->fields['RES'] == self::PAYMENT_ANNOUNCED)
		{
			$this->status = TransactionStatuses::PENDING;
		}
		else
		{
			$this->status = TransactionStatuses::FAILURE;
		}

		return $this->status;
	}

	protected function sign()
	{
		$fields = [
			'AID', 'TYP', 'AMT', 'CUR', 'REF', 'RES', 'TID', 'OID', 'TSS',
			'CardId', 'CardMask', 'CardExp', 'AuthNumber', 'CardRecTxSec',
			'CardAcquirerResponseId'
		];

		return (new Hmac($this->auth))->sign(implode(
			'', array_map(function($field) { return $field; }, $fields)
		));
	}
}
