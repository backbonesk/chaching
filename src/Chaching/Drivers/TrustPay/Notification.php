<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TrustPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;

final class Notification extends \Chaching\Messages\Hmac
{
	public $status 				= FALSE;
	public $reference_number 	= NULL;

	const PAYMENT_SUCCESS 		= 1;
	const PAYMENT_ANNOUNCED 	= 2;
	const PAYMENT_AUTHORIZED 	= 3;
	const PAYMENT_PROCESSING 	= 4;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'AID', 'TYP', 'AMT', 'CUR', 'REF', 'RES', 'TID', 'OID', 'TSS',
			'SIG',
			'CardId', 'CardMask', 'CardExp', 'AuthNumber', 'CardRecTxSec',
			'CardAcquirerResponseId', 'SIG2'
		);

		foreach ($this->readonly_fields as $field_name)
		{
			$this->fields[ $field_name ] = isset($options[ $field_name ])
				? $options[ $field_name ]
				: NULL;
		}

		$this->set_authorization($authorization);

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		$signature = $this->sign($this->signature_base());

		if ($this->fields['SIG2'] !== $signature)
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIG2']
			));

		$this->reference_number 	= $this->fields['REF'];

		$correct_statuses = array(
			self::PAYMENT_SUCCESS,
			self::PAYMENT_AUTHORIZED,
			self::PAYMENT_PROCESSING
		);

		if (in_array($this->fields['RES'], $correct_statuses))
		{
			$this->status = \Chaching\Statuses::SUCCESS;
		}
		else if ($this->fields['RES'] == self::PAYMENT_ANNOUNCED)
		{
			$this->status = \Chaching\Statuses::PENDING;
		}
		else
		{
			$this->status = \Chaching\Statuses::FAILURE;
		}

		return $this->status;
	}

	protected function signature_base()
	{
		return $this->fields['AID'] . $this->fields['TYP'] .
			$this->fields['AMT'] . $this->fields['CUR'] . $this->fields['REF'] .
			$this->fields['RES'] . $this->fields['TID'] . $this->fields['OID'] .
			$this->fields['TSS'] . $this->fields['CardId'] .
			$this->fields['CardMask'] . $this->fields['CardExp'] .
			$this->fields['AuthNumber'] . $this->fields['CardRecTxSec'] .
			$this->fields['CardAcquirerResponseId'];
	}
}
