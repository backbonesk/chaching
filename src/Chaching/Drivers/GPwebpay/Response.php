<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\GPwebpay;

use \Chaching\Encryption\PemKeys;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $card_id 			= NULL;

	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [
			'OPERATION', 'ORDERNUMBER', 'MERORDERNUM', 'MD', 'PRCODE',
			'SRCODE', 'RESULTTEXT', 'DETAILS', 'USERPARAM1', 'ADDINFO',
			'DIGEST', 'DIGEST1'
		];

		foreach ($this->readonly_fields as $field)
		{
			if(array_key_exists($field, $attributes))
			{
				$this->fields[ $field ] = $attributes[ $field ];
			}
			else
			{
				$this->fields[ $field ] = NULL;
			}
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
		if (!$this->verify($this->fields['DIGEST1']))
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s'). If this persists contact the bank.",
				$this->fields['DIGEST1']
			));

		$this->variable_symbol = $this->fields['MERORDERNUM'] != NULL ? $this->fields['MERORDERNUM'] : $this->fields['ORDERNUMBER'];

		$this->status = empty($this->fields['PRCODE'])
			? TransactionStatuses::SUCCESS
			: TransactionStatuses::FAILURE;

		return $this->status;
	}

	protected function verify($given_signature)
	{
		$signature_base 	= '';
		$fields 			= array_slice($this->readonly_fields, 0, 10);

		foreach ($fields as $field)
		{
			if ($this->fields[ $field ] === NULL)
				continue;

			if (!empty($signature_base))
			{
				$signature_base .= '|';
			}

			$signature_base .= $this->fields[ $field ];
		}

		$signature_base .= sprintf('|%s', $this->auth[ 0 ]);

		return (new PemKeys($this->auth))->verify(
			$given_signature, $signature_base
		);
	}
}
