<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\VUBeCard;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Drivers\VUBeCard;
use \Chaching\Encryption\Base64;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $card_no 			= NULL;
	public $card_expire_on 		= NULL;
	public $transaction_id 		= NULL;
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'HASHPARAMS', 'HASHPARAMSVAL', 'HASH', 'hashAlgorithm' ];
		$this->required_fields = [ 'clientid', 'oid' ];

		$this->fields = $attributes;

		$this->set_authorization($authorization);

		if (!empty($options))
		{
			$this->set_options($options);
		}

		$this->validate();

		unset(
			$this->fields['HASH'],
			$this->fields['HASHPARAMS'],
			$this->fields['HASHPARAMSVAL'],
			$this->fields['hashAlgorithm']
		);
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		$this->validate_required_fields();

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
				$signature, $this->fields['HASH']
			));

		$this->variable_symbol 	= $this->fields['oid'];

		$response = !empty($this->fields['Response'])
			? strtolower($this->fields['Response'])
			: 'error';

		switch ($response)
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

		if (!empty($this->fields['TransId']))
		{
			$this->transaction_id = $this->fields['TransId'];
		}

		if (!empty($this->fields['MaskedPan']))
		{
			$this->card_no = $this->fields['MaskedPan'];
		}

		if (!empty($this->fields['Ecom_Payment_Card_ExpDate_Year']) AND !empty($this->fields['Ecom_Payment_Card_ExpDate_Month']))
		{
			$year = (int) substr(
				$this->fields['Ecom_Payment_Card_ExpDate_Year'], 0, 2
			) + 2000;

			$month = (int) substr(
				$this->fields['Ecom_Payment_Card_ExpDate_Month'], 0, 2
			);

			$this->card_expire_on = new \DateTime(sprintf('%d-%d-%d',
				$year, $month,
				date('t', strtotime($year . '-' . $month . '-01'))
			));
		}

		return $this->status;
	}

	protected function sign()
	{
		$signature_base = '';

		if (isset($this->fields['HASHPARAMS']))
		{
			if (!empty($this->fields['hashAlgorithm']) AND $this->fields['hashAlgorithm'] === VUBeCard::HASH_ALGORITHM_VERSION_2)
			{
				$params = explode('|', $this->fields['HASHPARAMS']);

				foreach ($params as $param)
				{
					if (isset($this->fields[ $param ]))
					{
						$signature_base .= $this->escape_special_chars($this->fields[ $param ]);
					}

					$signature_base .= '|';
				}

				$signature_base .= $this->escape_special_chars($this->auth[ 1 ]);
				$hash_algorithm = 'sha512';
			}
			else
			{
				$params = explode(':', $this->fields['HASHPARAMS']);

				foreach ($params as $param)
				{
					if (isset($this->fields[ $param ]))
					{
						$signature_base .= $this->fields[ $param ];
					}
				}

				$signature_base .= $this->auth[ 1 ];
				$hash_algorithm = 'sha1';
			}
		}

		return (new Base64($this->auth))->sign($signature_base, $hash_algorithm);
	}
}
