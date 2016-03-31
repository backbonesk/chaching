<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\VUBeCard;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Base64;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = [ 'HASHPARAMS', 'HASHPARAMSVAL', 'HASH' ];

		$this->fields = $options;

		$this->set_authorization($authorization);

		$this->validate();

		unset(
			$this->fields['HASH'],
			$this->fields['HASHPARAMS'],
			$this->fields['HASHPARAMSVAL']
		);
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		if (!isset($this->auth[ 1 ]) OR empty($this->auth[ 1 ]))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the secret key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		$signature = $this->sign();

		if ($this->fields['HASH'] !== $signature)
			throw new InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIGN']
			));

		$this->variable_symbol 	= $this->fields['oid'];

		switch (strtolower($this->fields['Response']))
		{
			case 'approved':
				$this->status = TransactionStatuses::SUCCESS;
				break;

			case 'declined':
				$this->status = TransactionStatuses::FAILURE;
				break;

			default:
 			case 'error':
 				$this->status = TransactionStatuses::FAILURE;
				break;
		}

		return $this->status;
	}

	protected function sign()
	{
		$signature_base = '';

		if (isset($this->fields['HASHPARAMS']))
		{
			$params = explode(':', $this->fields['HASHPARAMS']);

			foreach ($params as $param)
			{
				$signature_base .= isset($this->fields[ $param ])
					? $this->fields[ $param ]
					: '';
			}
		}

		$signature_base .= $this->auth[ 1 ];

		return (new Base64($this->auth))->sign($signature_base);
	}
}
