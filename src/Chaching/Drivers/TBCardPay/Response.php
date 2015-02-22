<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2015 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TBCardPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Encryption\Des;
use \Chaching\Encryption\Aes256;
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

		$this->readonly_fields = array(
			'VS', 'RES', 'SIGN', 'AC', 'TRES'
		);

		$this->fields = array(
			'VS' 	=> (isset($options['VS']) AND !empty($options['VS']))
				? $options['VS']
				: NULL,
			'RES' 	=> (isset($options['RES']) AND !empty($options['RES']))
				? $options['RES']
				: NULL,
			'SIGN' 	=> (isset($options['SIGN']) AND !empty($options['SIGN']))
				? $options['SIGN']
				: NULL,
			'AC' 	=> (isset($options['AC']) AND !empty($options['AC']))
				? $options['AC']
				: NULL
		);

		if (isset($options['TRES']))
		{
			$this->readonly_fields[] = 'TRES';
			$this->readonly_fields[] = 'CID';

			$this->fields = array_merge($this->fields, array(
				'TRES' 	=> (isset($options['TRES']) AND !empty($options['TRES']))
					? $options['TRES']
					: NULL,
				'CID' 	=> (isset($options['CID']) AND !empty($options['CID']))
					? $options['CID']
					: NULL
			));
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
		$signature = $this->sign();

		if ($this->fields['SIGN'] !== $signature)
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIGN']
			));

		$this->variable_symbol = $this->fields['VS'];

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
		$signature_base = (isset($this->fields['TRES']) AND !empty($this->fields['TRES']))
			? $this->fields['VS'] . $this->fields['TRES'] . $this->fields['AC'] . $this->fields['CID']
			: $this->fields['VS'] . $this->fields['RES'] . $this->fields['AC'];

		if (strlen($this->auth[ 1 ]) === 8)
			return (new Des($this->auth))->sign($signature_base);

		return (new Aes256($this->auth))->sign($signature_base);
	}
}
