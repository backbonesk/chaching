<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\GPwebpay;

use \Chaching\Driver;
use \Chaching\Encryption\PemKeys;
use \Chaching\TransactionStatuses;
use \Chaching\Exceptions\InvalidOptionsException;


class Response extends \Chaching\Message
{
	public $card_id 			= NULL;

	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = [
			'OPERATION', 'ORDERNUMBER', 'MERORDERNUM', 'MD',
			'PRCODE', 'SRCODE', 'RESULTTEXT', 'DIGEST', 'DIGEST1'
		];

		$this->fields = [
			'OPERATION' 	=> isset($options['OPERATION'])
				? $options['OPERATION']
				: NULL,
			'ORDERNUMBER' 	=> isset($options['ORDERNUMBER'])
				? $options['ORDERNUMBER']
				: NULL,
			'MERORDERNUM' 	=> (isset($options['MERORDERNUM']) AND !empty($options['MERORDERNUM']))
				? $options['MERORDERNUM']
				: NULL,
			'MD' 			=> (isset($options['MD']) AND !empty($options['MD']))
				? $options['MD']
				: NULL,
			'PRCODE' 		=> isset($options['PRCODE'])
				? $options['PRCODE']
				: NULL,
			'SRCODE' 		=> isset($options['SRCODE'])
				? $options['SRCODE']
				: NULL,
			'RESULTTEXT' 	=> (isset($options['RESULTTEXT']) AND !empty($options['RESULTTEXT']))
				? $options['RESULTTEXT']
				: NULL,
			'DIGEST1' 	=> (isset($options['DIGEST1']) AND !empty($options['DIGEST1']))
				? $options['DIGEST1']
				: NULL
		];

		$this->set_authorization($authorization);

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		if ($this->verify($this->fields['DIGEST1']))
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['DIGEST1']
			));

		$this->variable_symbol = $this->fields['ORDER_NUMBER'];

		$this->status = ($this->fields['PRCODE'] == 0 OR empty($this->fields['PRCODE']))
			? TransactionStatuses::SUCCESS
			: TransactionStatuses::FAILURE;

		return $this->status;
	}

	protected function verify($given_signature)
	{
		$signature_base 	= '';
		$fields 			= [
			'OPERATION', 'ORDERNUMBER', 'MERORDERNUM', 'MD', 'PRCODE',
			'SRCODE', 'RESULTTEXT'
		];

		foreach ($fields as $field)
		{
			if (!empty($signature_base))
			{
				$signature_base .= '|';
			}

			$signature_base .= $this->fields[ $field ];
		}

		$signature_base .= sprintf('|%s', $this->auth[ 0 ]);

		return (new PemKeys($this->auth))->verify(
			$given_stignature, $signature_base
		);
	}
}
