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

use \Chaching\Chaching;
use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidRequestException;
use \Chaching\Transport\Curl;


class Request extends \Chaching\Message
{
	public $transaction_id 		= NULL;

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'command' ];

		$this->required_fields = [
			'account', 'amount', 'currency', 'client_ip_addr'
		];

		$this->optional_fields = [ 'description', 'language' ];

		$this->field_map = [
			Driver::AMOUNT 				=> 'amount',
			Driver::CURRENCY 			=> 'currency',
			Driver::CLIENT_IP 			=> 'client_ip_addr',
			Driver::DESCRIPTION 		=> 'description',
			Driver::LANGUAGE 			=> 'language',
			Driver::VARIABLE_SYMBOL 	=> 'account'
		];

		$this->set_authorization($authorization);

		$this->fields['command'] 			= 'v';
		$this->fields['currency'] 			= Currencies::EUR;
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
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	protected function validate()
	{
		if (!is_array($this->auth) OR count($this->auth) !== 2)
			throw new InvalidAuthorizationException(
				"Merchant information incorrect. In case of PB iTerminal, " .
				"you need to provide an array with "
			);

		if (!is_array($this->auth[ 1 ]))
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
				"Field %s has an unacceptable value '%s'. The easiest way " .
				"is to use constants provided in `\Chaching\Currencies` " .
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

		if (!empty($this->fields['account']) AND strlen($this->fields['account']) > 28)
			throw new InvalidOptionsException(sprintf(
				"Field %s (or account) has an unacceptable value '%s'. " .
				"Valid variable symbol consists of up to 28 digits (your " .
				"value has %d).", Driver::VARIABLE_SYMBOL,
				$this->fields['account'], strlen($this->fields['account'])
			));

		// Optional fields
		if (!empty($this->fields['language']) AND strlen($this->fields['language']) > 32)
			throw new InvalidOptionsException(sprintf(
				"Field %s has an unacceptable value '%s'. Valid value " .
				"consists of up to 32 characters (your value has %d).",
				$this->fields['language'], strlen($this->fields['language'])
			));

		if (!empty($this->fields['description']))
		{
			$this->fields['description'] = urldecode(
				$this->fields['description']
			);

			$this->fields['description'] = htmlspecialchars(
				$this->fields['description'], ENT_QUOTES
			);

			if (strlen($this->fields['description']) > 125)
				throw new InvalidOptionsException(sprintf(
					"Field %s has an unacceptable urlencoded value " .
					"'%s'. Valid value consists of up to 125 characters " .
					"(your value has %d).", Driver::DESCRIPTION,
					strlen($this->fields['description'])
				));
		}
	}

	/**
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	public function process($redirect = TRUE)
	{
		$this->validate();

		$request = new Curl(
			Curl::METHOD_POST,
			$this->request_server_url(),
			http_build_query($this->fields),
			[
				CURLOPT_SSLKEYPASSWD 	=> $this->auth[ 1 ]['password'],
				CURLOPT_SSLCERT 		=> $this->auth[ 1 ]['keystore'],
				CURLOPT_CAINFO 			=> $this->auth[ 1 ]['keystore'],
				CURLOPT_SSL_VERIFYPEER 	=> TRUE,
				CURLOPT_VERBOSE 		=> TRUE
			]
		);

		if (preg_match('/^TRANSACTION_ID: (?<transaction_id>[a-zA-Z0-9+\/]+={0,2})/', $request->content(), $matches) AND !empty($matches['transaction_id']))
		{
			$this->transaction_id = $matches['transaction_id'];

			$redirection = sprintf(
				'%s?trans_id=%s',
				$this->request_client_url(),
				$this->transaction_id
			);

			if ($redirect === TRUE)
			{
				trigger_error(
					"Due to the fact that request ends with `trans_id` " .
					"which is a unique identifier of transaction, it is not " .
					"possible to redirect from within chaching as you " .
					"should really save it for later to be able to read " .
					"the response from the bank. It is possible to get the " .
					"value through `transaction_id` property", E_USER_NOTICE
				);
			}

			return $redirection;
		}

		throw new InvalidRequestException(sprintf(
			"Incorrect response from the bank: %s", $request->content()
		));
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://secureshop-test.firstdata.lv:8443/ecomm/MerchantHandler'
			: 'https://secureshop.firstdata.lv:8443/ecomm/MerchantHandler';
	}

	private function request_client_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://secureshop.firstdata.lv:8443/ecomm/MerchantHandler'
			: 'https://secureshop.firstdata.lv/ecomm/ClientHandler';
	}
}
