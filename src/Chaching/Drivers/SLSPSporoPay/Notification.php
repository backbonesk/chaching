<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\SLSPSporoPay;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\TripleDes;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;


class Notification extends \Chaching\Message
{
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [
			'pu_predcislo', 'pu_cislo', 'pu_kbanky', 'suma', 'mena', 'vs',
			'ss', 'result', 'real', 'sign3'
		];

		foreach ($this->readonly_fields as $field)
		{
			$this->fields[ $field ] = (isset($attributes[ $field ]) AND !empty($attributes[ $field ]))
				? $attributes[ $field ]
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

		if ($this->fields['sign3'] !== $signature)
			throw new InvalidResponseException(sprintf(
				"Signature received as part of the notfication is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature,
				$this->fields['sign3']
			));

		$this->fields['result'] 	= strtolower($this->fields['result']);
		$this->fields['real'] 		= strtolower($this->fields['real']);

		$this->variable_symbol 		= $this->fields['vs'];

		if ($this->fields['result'] === 'ok')
		{
			$this->status = $this->fields['real'] === 'ok'
				? TransactionStatuses::SUCCESS
				: TransactionStatuses::PENDING;
		}
		else
		{
			$this->status = TransactionStatuses::FAILURE;
		}

		return $this->status;
	}

	protected function sign()
	{
		$field_list 		= [
			'pu_predcislo', 'pu_cislo', 'pu_kbanky', 'suma', 'mena', 'vs',
			'ss', 'result', 'real'
		];

		$signature_base 	= '';

		foreach ($field_list as $field)
		{
			if (!empty($signature_base))
			{
				$signature_base .= ';';
			}

			$signature_base .= isset($this->fields[ $field ])
				? $this->fields[ $field ]
				: '';
		}

		return (new TripleDes($this->auth))->sign($signature_base);
	}
}
