<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TBTatraPay;

use \Chaching\Driver;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\ServiceStatuses;
use \Chaching\Transport\Curl;


class Status extends \Chaching\Message
{
	const REQUEST_SERVER_URI 	=
		'https://moja.tatrabanka.sk/cgi-bin/e-commerce/start/isoffline.jsp';

	public $status 				= ServiceStatuses::OFFLINE;
	public $verbose_response 	= '';

	public function __construct(Array $authorization)
	{
		parent::__construct();

		$this->readonly_fields = [ 'MID', 'SERVICE', 'TIMESTAMP', 'HMAC' ];

		$this->set_authorization($authorization);

		$this->fields['SERVICE'] = 'DOMPAYMENT';

		// Timestamp used in communication with the bank has to be in UTC.
		// Used only with HMAC encoding.
		$old_timezone = date_default_timezone_get();
		date_default_timezone_set('UTC');

		$this->fields['TIMESTAMP'] = date('dmYHis');

		date_default_timezone_set($old_timezone);

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

		if (!isset($this->auth[ 1 ]) OR empty($this->auth[ 1 ]))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the secret key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		if (strlen($this->auth[ 1 ]) !== 128)
			throw new InvalidAuthorizationException(
				"The option to check status of TatraPay service " .
				"is avialable only if you use HMAC message encryption " .
				"(shared secret has 128 characters). If you have " .
				"a different one, you would have to contact the bank."
			);

		// Validate all required fields first
		$this->validate_required_fields();

		$this->fields['HMAC'] = $this->sign();

		$request = new Curl(
			Curl::METHOD_POST, self::REQUEST_SERVER_URI,
			http_build_query($this->fields),
			[
				CURLOPT_SSL_VERIFYPEER 	=> TRUE
			]
		);

		$this->verbose_response = $request->content();

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

		if (isset($response->error) AND isset($response->error->error_code))
		{
			$error_code = (int) $response->error->error_code;

			switch ($error_code)
			{
				// This should never happen.
				case 1002:
					$error_message = sprintf(
						"Invalid signature (SIGNATURE: %s) or service that " .
						"is being checked (SERVICE: %s)",
						!empty($this->fields['HMAC'])
							? $this->fields['HMAC']
							: 'none',
						!empty($this->fields['SERVICE'])
							? $this->fields['SERVICE']
							: 'none'
					);
					break;

				// This should never happen.
				case 1003:
					$error_message = sprintf(
						"Invalid timestamp (TIMESTAMP: %s)",
						!empty($this->fields['TIMESTAMP'])
							? $this->fields['TIMESTAMP']
							: 'none'
					);
					break;

				case 1101:
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

		$this->status = (string) $response->result->status;

		$signature = strtolower((new Hmac($this->auth))->sign(sprintf(
			'%s%s%s%s',
			$response->request->mid,
			$response->request->timestamp,
			$response->request->service,
			$this->status
		)));

		if ($signature !== (string) $response->result->hmac)
			throw new InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature,
				$response->result->hmac
			));


		return $this->status;
	}

	protected function sign()
	{
		$field_list = [ 'MID', 'TIMESTAMP', 'SERVICE' ];

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
}
