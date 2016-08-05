<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\PBiTerminal;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\TransactionStatuses;
use \Chaching\Transport\Curl;


class Refund extends \Chaching\Message
{
	const REQUEST_SERVER_URI 	=
		'https://secureshop-test.firstdata.lv:8443/ecomm/MerchantHandler';

	public $status 				= FALSE;
	public $transaction_id 		= NULL;

	public $verbose_response 	= '';

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [
			'command', 'RESULT', 'RESULT_CODE'
		];

		$this->required_fields = [
			'trans_id', 'amount', 'currency', 'client_ip_addr'
		];

		$this->set_authorization($authorization);

		$this->field_map = [
			Driver::AMOUNT 			=> 'amount',
			Driver::CURRENCY 		=> 'currency',
			Driver::CLIENT_IP 		=> 'client_ip_addr',
			Driver::TRANSACTION_ID 	=> 'trans_id',
		];

		$this->fields['command'] 			= 'r';
		$this->fields['client_ip_addr'] 	= isset($_SERVER['REMOTE_ADDR'])
			? $_SERVER['REMOTE_ADDR']
			: $_SERVER['SERVER_ADDR'];

		if (!empty($attributes))
		{
			$this->set_attributes($attributes);
		}

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
		if (!is_array($this->auth) OR count($this->auth) !== 2 OR !is_array($this->auth[ 1 ]))
			throw new InvalidAuthorizationException(
				"Incorrect settings for shared secret. In case of " .
				"iTerminal, you need to provide an array with " .
				"`keystore` key with paths to '.pem' certificate and " .
				"`password` key with corresponding password."
			);

		if (!isset($this->auth[ 1 ]['keystore']) OR empty($this->auth[ 1 ]['keystore']))
			throw new InvalidAuthorizationException(
				"Authorization information (`keystore` key) is missing " .
				"a value. Try changing it to a file path with the '.pem' " .
				"file you generated according to the documentation."
			);

		if (!is_file($this->auth[ 1 ]['keystore']) OR !is_readable($this->auth[ 1 ]['keystore']))
		{
			throw new InvalidAuthorizationException(sprintf(
				"Authorization information (`keystore` key) has " .
				"an unacceptable value pointing to a missing or unreadable ".
				"file '%s'. Upload the file and set the file path " .
				"to it's new location.", $this->auth[ 1 ]['keystore']
			));
		}

		if (!isset($this->auth[ 1 ]['password']) OR empty($this->auth[ 1 ]['password']))
			throw new InvalidAuthorizationException(
				"The password to certificate provided in `password` is " .
				"missing value."
			);

		if (!empty($this->transaction_id))
		{
			$this->fields['trans_id'] = $this->transaction_id;
		}

		// Validate all required fields first
		$this->validate_required_fields();


		if (is_string($this->fields['currency']) AND !is_numeric($this->fields['currency']))
		{
			$currency = Currencies::get($this->fields['currency']);

			$this->fields['currency'] = ($currency !== NULL)
				? $currency['numeric_code']
				: NULL;
		}

		if (Currencies::validate_code($this->fields['currency']) === FALSE)
			throw new InvalidOptionsException(sprintf(
				"Field currency has an unacceptable value '%s'. The easiest " .
				"way is to use constants provided in `\Chaching\Currencies` " .
				"with currency codes based on ISO 4217.",
				$this->fields['currency']
			));

		if (!isset($currency))
		{
			$currency = Currencies::get($this->fields['currency']);
		}

		if (is_numeric($this->fields['amount']))
		{
			$this->fields['amount'] *= pow(10, $currency['minor_unit']);
		}

		if (strlen($this->fields['amount']) > 12)
			throw new InvalidOptionsException(sprintf(
				"Field %s has an unacceptable value '%s'. Valid " .
				"amount (in smallest units of currency) consists of up to " .
				"12 digits (your amount has %d).",
				$this->fields['amount'], strlen($this->fields['amount'])
			));

		$request = new Curl(
			Curl::METHOD_POST, self::REQUEST_SERVER_URI,
			http_build_query($this->fields),
			[
				CURLOPT_SSLKEYPASSWD 	=> $this->auth[ 1 ]['password'],
				CURLOPT_SSLCERT 		=> $this->auth[ 1 ]['keystore'],
				CURLOPT_CAINFO 			=> $this->auth[ 1 ]['keystore'],
				CURLOPT_SSL_VERIFYPEER 	=> TRUE,
				CURLOPT_VERBOSE 		=> TRUE
			]
		);

		$this->transaction_id 		= $this->fields['trans_id'];
		$this->verbose_response 	= $request->content();

		if (preg_match_all('/([A-Z0-9\_]+): (.+)\s*/', $this->verbose_response, $matches))
		{
			foreach ($matches[ 1 ] as $key => $header)
			{
				$header = strtoupper($header);

				switch ($header)
				{
					case 'RESULT':
						if ($matches[ 2 ][ $key ] === 'OK')
						{
							$this->status = TransactionStatuses::SUCCESS;
						}
						else if ($matches[ 2 ][ $key ] === 'REVERSED')
						{
							$this->status = TransactionStatuses::REVERSED;
						}
						else
						{
							$this->status = TransactionStatuses::FAILURE;
						}
						break;

					default:
						$this->fields[ $header ] = $matches[ 2 ][ $key ];
						break;
				}
			}
		}

		return $this->status;
	}
}
