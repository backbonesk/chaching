<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\SLSPSporoPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;

final class Response extends \Chaching\Messages\TripleDes
{
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'u_predcislo', 'u_cislo', 'u_kbanky', 'pu_predcislo', 'pu_cislo',
			'pu_kbanky', 'suma', 'mena', 'vs', 'ss', 'url', 'param', 'result',
			'real', 'SIGN2'
		);

		foreach ($this->readonly_fields as $field)
		{
			$this->fields[ $field ] = isset($options[ $field ])
				? $options[ $field ]
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

		if ($this->fields['SIGN2'] !== $signature)
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIGN2']
			));

		$this->fields['result'] 	= strtolower($this->fields['result']);
		$this->fields['real'] 		= strtolower($this->fields['real']);

		$this->variable_symbol 		= $this->fields['vs'];

		if ($this->fields['result'] === 'ok')
		{
			$this->status = $this->fields['real'] === 'ok'
				? \Chaching\Statuses::SUCCESS
				: \Chaching\Statuses::PENDING;
		}
		else
		{
			$this->status = \Chaching\Statuses::FAILURE;
		}

		return $this->status;
	}

	protected function signature_base()
	{
		$field_list 		= [
			'u_predcislo', 'u_cislo', 'u_kbanky', 'pu_predcislo', 'pu_cislo',
			'pu_kbanky', 'suma', 'mena', 'vs', 'ss', 'url', 'param', 'result',
			'real'
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

		return $signature_base;
	}
}
