<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TBCardPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;
use \Chaching\Transport\Curl;


class Refund extends \Chaching\Message implements \Chaching\ECDSAResponseInterface
{
	use \Chaching\ECDSAResponseValidator;

	const REQUEST_SERVER_URI 	=
		'https://moja.tatrabanka.sk/cgi-bin/e-commerce/start/txn_process.jsp';

	public $status 				= FALSE;
	public $verbose_response 	= '';

	private $signature_base 	= '';

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'MID', 'TXN', 'TIMESTAMP', 'HMAC' ];
		$this->required_fields = [ 'TID', 'AMT' ];
		$this->optional_fields = [ 'VS', 'REM' ];

		$this->set_authorization($authorization);

		$this->field_map = [
			Driver::AMOUNT 				=> 'AMT',
			Driver::RETURN_EMAIL 		=> 'REM',
			Driver::TRANSACTION_ID 		=> 'TID',
			Driver::VARIABLE_SYMBOL 	=> 'VS'
		];

		$this->fields['TXN'] = 'CB';

		// Timestamp used in communication with the bank has to be in UTC.
		// Used only with HMAC encoding.
		$old_timezone = date_default_timezone_get();
		date_default_timezone_set('UTC');

		$this->fields['TIMESTAMP'] = date('dmYHis');

		date_default_timezone_set($old_timezone);

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
		if (!is_array($this->auth) OR count($this->auth) !== 2)
			throw new InvalidAuthorizationException(
				"Merchant authorization information is missing."
			);

		$this->fields['MID'] = isset($this->auth[ 0 ])
			? $this->auth[ 0 ]
			: '';

		if (!preg_match('/^[a-z0-9]{3,4}$/', $this->fields['MID']))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (Merchant ID or MID) has an " .
				"unacceptable value '%s'. Try changing it to value you " .
				"got from the bank.", $this->fields['MID']
			));

		if (!isset($this->auth[ 1 ]) OR empty($this->auth[ 1 ]))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the secret key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		if (strlen($this->auth[ 1 ]) !== 128)
			throw new InvalidAuthorizationException(
				"The option to refund a payment is avialable only if you " .
				"use HMAC message encryption (shared secret has " .
				"128 characters). If you have a different one, you would " .
				"have to contact the bank."
			);

		if (!isset($this->fields['TID']))
		{
			if (!preg_match('/^[0-9]{1,10}$/', $this->fields['VS']))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or VS) has an unacceptable value '%s'. Valid " .
					"variable symbol consists of up to 10 digits.",
					Driver::VARIABLE_SYMBOL, $this->fields['VS']
				));
		}

		// Validate all required fields first
		$this->validate_required_fields();

		if (!is_string($this->fields['AMT']))
		{
			$this->fields['AMT'] = sprintf('%01.2F', $this->fields['AMT']);
		}

		if (!preg_match('/^[0-9]{1,9}(\.[0-9]{1,2})?$/', $this->fields['AMT']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or AMT) has an unacceptable value '%s'. Valid " .
				"amount consists of up to 9 base numbers and maximum of two " .
				"decimals separated by a dot ('.').",
				Driver::AMOUNT, $this->fields['AMT']
			));

		if (isset($this->fields['REM']))
		{
			if (!filter_var($this->fields['REM'], FILTER_VALIDATE_EMAIL))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or REM) has an unacceptable value '%s'. Valid " .
					"return email address has to be properly formatted.",
					Driver::RETURN_EMAIL, $this->fields['REM']
				));
		}

		$this->fields['HMAC'] = $this->sign();

		$request = new Curl(
			Curl::METHOD_POST, self::REQUEST_SERVER_URI,
			http_build_query($this->fields),
			[
				CURLOPT_SSL_VERIFYPEER 	=> TRUE
			]
		);

		$this->verbose_response = $request->content();

		$authorization = explode(
			', ', (string) $request->headers('authorization')
		);

		$auth_fields = [];

		foreach ($authorization as $header)
		{
			if (preg_match('/([\_A-Z]+)=([a-z0-9]+)/i', $header, $matches))
			{
				$auth_fields[ $matches[ 1 ] ] = $matches[ 2 ];
			}
		}

		$this->signature_base = sprintf(
			'%s%s', $this->verbose_response, $auth_fields['HMAC']
		);

		list($status, $error_message) = $this->validate_ecdsa_signature(
			!empty($auth_fields['ECDSA']) ? $auth_fields['ECDSA'] : '',
			!empty($auth_fields['ECDSA_KEY']) ? $auth_fields['ECDSA_KEY'] : 0
		);

		if ($status !== TRUE)
			throw new InvalidResponseException($error_message);

		if (($response = simplexml_load_string($this->verbose_response)) === FALSE)
		{
			$message = (($error = libxml_get_last_error()) !== FALSE)
				? $error->message
				: "Unknown";

			throw new InvalidResponseException(sprintf(
				"There was an error with parsing the XML response from " .
				"the bank: %s (%s)",
				$message,
				$this->verbose_response
			));
		}

		if (!isset($response->result->res))
			throw new InvalidResponseException(sprintf(
				"There was an error with invalid response from the bank" .
				"the bank: `res` object cannot be found (%s)",
				$this->verbose_response
			));

		$this->status = TransactionStatuses::FAILURE;

		if (isset($response->result->errorCode))
		{
			$error_code = (int) $response->result->errorCode;

			switch ($error_code)
			{
				case 3:
					$error_message = sprintf(
						"Input amount (AMT: %s)",
						!empty($this->fields['AMT'])
							? $this->fields['AMT']
							: 'none'
					);
					break;

				case 4:
					$error_message = sprintf(
						"Invalid variable symbol: %s)",
						!empty($this->fields['VS'])
							? $this->fields['VS']
							: 'none'
					);
					break;

				// This should never happen.
				case 9:
					$error_message = sprintf(
						"Invalid operation (TXN: %s).",
						!empty($this->fields['TIMESTAMP'])
							? $this->fields['TIMESTAMP']
							: 'none'
					);
					break;

				// This should never happen.
				case 10:
					$error_message = sprintf(
						"Invalid MID (MID: %s) or security signature.",
						!empty($this->fields['MID'])
							? $this->fields['MID']
							: 'none'
					);
					break;

				// This should never happen.
				case 11:
					$error_message = "Some required parameter is missing.";
					break;

				// This should never happen.
				case 15:
					$error_message = sprintf(
						"Invalid timestamp (TIMESTAMP: %s)",
						!empty($this->fields['TIMESTAMP'])
							? $this->fields['TIMESTAMP']
							: 'none'
					);
					break;

				case 16:
					$error_message = sprintf(
						"Invalid transaction identifier – a payment with " .
						"given variable symbol (VS: %s) or transaction ID " .
						"(TID: %s) could not be found.",
						!empty($this->fields['VS'])
							? $this->fields['VS']
							: 'none',
						!empty($this->fields['TID'])
							? $this->fields['TID']
							: 'none'
					);
					break;

				case 18:
					$error_message = sprintf(
						"Multiple transactions with given variable symbol " .
						"%s found.",
						!empty($this->fields['VS'])
							? $this->fields['VS']
							: 'none'
					);
					break;

				case 19:
					$error_message = sprintf(
						"Invalid amount (AMT: %s). Amount has to be lower " .
						"or equal than the original amount minus all " .
						"prior refunds.",
						!empty($this->fields['AMT'])
							? $this->fields['AMT']
							: 'none'
					);
					break;

				case 20:
					$error_message = sprintf(
						"Refund cannot happen more than 366 days after " .
						"original transaction (TIMESTAMP: %s).",
						!empty($this->fields['TIMESTAMP'])
							? $this->fields['TIMESTAMP']
							: 'none'
					);
					break;

				case 13:
				default:
					$error_message = 'Unknown error.';
					break;
			}

			throw new InvalidResponseException(sprintf(
				"There was an error with processing at the bank and the " .
				"transaction has not been executed. Error%s: %s.",
				!empty($error_code) ? sprintf(' #%d', $error_code) : '',
				$error_message
			));
		}

		$this->transaction_id 	= $response->request->tid;
		$this->variable_symbol 	= $response->request->vs;
		$this->amount 			= $response->request->amt;

		$status = (string) $response->result->res;

		if ($status === 'OK')
		{
			$this->status = TransactionStatuses::REVERSED;
		}

		return $this->status;
	}

	protected function sign()
	{
		$field_list = [ 'MID', 'AMT', 'TID', 'VS', 'TXN', 'REM', 'TIMESTAMP' ];

		$signature_base = '';

		foreach ($field_list as $field)
		{
			$signature_base .= isset($this->fields[ $field ])
				? $this->fields[ $field ]
				: '';
		}

		$encryption = new Hmac($this->auth);

		return strtolower($encryption->sign($signature_base));
	}

	public function ecdsa_signature_base()
	{
		return $this->signature_base;
	}
}
