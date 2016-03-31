<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\VUBePlatby;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = [ 'SS', 'VS', 'RES', 'SIGN' ];

		$this->fields = [
			'SS' 	=> (isset($options['SS']) AND !empty($options['SS']))
				? $options['SS']
				: NULL,
			'VS' 	=> (isset($options['VS']) AND !empty($options['VS']))
				? $options['VS']
				: NULL,
			'RES' 	=> (isset($options['RES']) AND !empty($options['RES']))
				? $options['RES']
				: NULL,
			'SIGN' 	=> (isset($options['SIGN']) AND !empty($options['SIGN']))
				? $options['SIGN']
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
		$signature = $this->sign();

		if ($this->fields['SIGN'] !== $signature)
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIGN']
			));

		$this->fields['RES'] = strtolower($this->fields['RES']);

		$this->variable_symbol 	= $this->fields['VS'];
		$this->status 			= ($this->fields['RES'] === 'ok' AND !empty($this->fields['VS']))
			? TransactionStatuses::SUCCESS
			: TransactionStatuses::FAILURE;

		return $this->status;
	}

	protected function sign()
	{
		$signature_base = $this->fields['VS'] . $this->fields['SS'] .
			$this->fields['RES'];

		return (new Hmac($this->auth))->sign($signature_base);
	}
}
