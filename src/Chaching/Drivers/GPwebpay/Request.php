<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2020 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\GPwebpay;

use \Chaching\Chaching;
use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Encryption\PemKeys;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;


class Request extends \Chaching\Message
{
	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'OPERATION', 'DEPOSITFLAG', 'DIGEST' ];
		$this->required_fields = [
			'MERCHANTNUMBER', 'ORDERNUMBER', 'AMOUNT', 'CURRENCY', 'URL'
		];

		$this->optional_fields = [ 'MERORDERNUM', 'MD', 'DESCRIPTION' ];

		$this->field_map = [
			Driver::AMOUNT 				=> 'AMOUNT',
			Driver::CURRENCY 			=> 'CURRENCY',
			Driver::DESCRIPTION 		=> 'DESCRIPTION',
			Driver::VARIABLE_SYMBOL 	=> 'ORDERNUMBER',
			Driver::CALLBACK 			=> 'URL'
		];

		$this->set_authorization($authorization);

		$this->fields['DEPOSITFLAG'] 	= 1;
		$this->fields['OPERATION'] 		= 'CREATE_ORDER';
		$this->fields['CURRENCY'] 		= Currencies::EUR;
		$this->fields['MERORDERNUM'] 	= '';
		$this->fields['MD'] 			= '';

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
			throw new InvalidAuthorizationException("Merchant ID is missing.");

		if (!is_array($this->auth[ 1 ]))
			throw new InvalidAuthorizationException(
				"Incorrect settings for shared secret. In case of " .
				"GP webpay, you need to provide an array with " .
				"`certificate` and `key` keys with paths " .
				"to corresponding private and public keys generated " .
				"according to the documentation."
			);

		if (!isset($this->auth[ 1 ]['certificate']) OR empty($this->auth[ 1 ]['certificate']))
			throw new InvalidAuthorizationException(
				"Authorization information (`certificate` key within " .
				"`shared_secret`) is missing value. Try changing it to " .
				"a file path with the '.crt' file you generated according " .
				"to the documentation."
			);

		if (!is_file($this->auth[ 1 ]['certificate']))
		{
			throw new InvalidAuthorizationException(sprintf(
				"Authorization information (`certificate` key within " .
				"`shared_secret`) has an unacceptable value pointing to a " .
				"missing file '%s'. Upload the file and set the file path " .
				"to it's new location.", $this->auth[ 1 ]['certificate']
			));
		}

		if (!isset($this->auth[ 1 ]['key']) OR empty($this->auth[ 1 ]['key']))
			throw new InvalidAuthorizationException(
				"Authorization information (`key` key within " .
				"`shared_secret`) is missing value. Try changing it to " .
				"a file path with the '.crt' file you generated according " .
				"to the documentation."
			);

		if (!is_file($this->auth[ 1 ]['key']))
		{
			throw new InvalidAuthorizationException(sprintf(
				"Authorization information (`key` key within " .
				"`shared_secret`) has an unacceptable value pointing to a " .
				"missing file '%s'. Upload the file and set the file path " .
				"to it's new location.", $this->auth[ 1 ]['certificate']
			));
		}

		if (!isset($this->auth[ 1 ]['passphrase']) OR empty($this->auth[ 1 ]['passphrase']))
			throw new InvalidAuthorizationException(
				"The passphrase to certificate provided in `certificate` " .
				"key within `shared_secret` is missing value."
			);

		$this->fields['MERCHANTNUMBER'] = isset($this->auth[ 0 ])
			? $this->auth[ 0 ]
			: '';

		if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $this->fields['MERCHANTNUMBER']))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (Merchant ID or Merchant number) " .
				"has an unacceptable value '%s'. Try changing it to value " .
				"you got from the bank.", $this->fields['MERCHANTNUMBER']
			));

		if (!isset($this->auth[ 1 ]) OR empty($this->auth[ 1 ]))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the secret key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		// Validate all required fields first
		$this->validate_required_fields();

		if (!is_string($this->fields['AMOUNT']))
		{
			$this->fields['AMOUNT'] = sprintf(
				'%01.2F', $this->fields['AMOUNT']
			);
		}

		if (!preg_match('/^[0-9]{1,15}(\.[0-9]+)?$/', $this->fields['AMOUNT']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or AMOUNT) has an unacceptable value '%s'. Valid " .
				"amount consists of up to 13 base digits and up to two " .
				"decimal places.", Driver::AMOUNT, $this->fields['AMOUNT']
			));

		if (is_string($this->fields['CURRENCY']) AND !is_numeric($this->fields['CURRENCY']))
		{
			$currency = Currencies::get($this->fields['CURRENCY']);

			$this->fields['CURRENCY'] = ($currency !== NULL)
				? $currency['numeric_code']
				: NULL;
		}

		if (Currencies::validate_code($this->fields['CURRENCY']) === FALSE)
			throw new InvalidOptionsException(sprintf(
				"Field %s (or CURRENCY) has an unacceptable value '%s'. " .
				"The easiest way is to use constants provided in " .
				"`\Chaching\Currencies` with currency codes based " .
				"on ISO 4217.", Driver::CURRENCY, $this->fields['CURRENCY']
			));

		if (!isset($currency))
		{
			$currency = Currencies::get($this->fields['CURRENCY']);
		}

		$this->fields['AMOUNT'] *= pow(10, $currency['minor_unit']);

		if (!preg_match('/^[0-9]{1,15}$/', $this->fields['ORDERNUMBER']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or ORDERNUMBER) has an unacceptable value '%s'. " .
				"Valid variable symbol consists of up to 15 digits.",
				Driver::VARIABLE_SYMBOL, $this->fields['ORDERNUMBER']
			));

		if (!filter_var($this->fields['URL'], FILTER_VALIDATE_URL))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or URL) has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['URL']
			));

		// Optional fields
		if (isset($this->fields['DESCRIPTION']) AND !empty($this->fields['DESCRIPTION']))
		{
			if (!preg_match('/[a-zA-Z0-9 \.,\_-]{1,255}/', $this->fields['DESCRIPTION']))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or DESCRIPTION) has an unacceptable value " .
					"'%s'. Valid description can not contain any accents " .
					"or fancy characters.", Driver::DESCRIPTION,
					$this->fields['DESCRIPTION']
				));
		}
	}

	protected function sign()
	{
		$signature_base 	= '';
		$fields 			= [
			'MERCHANTNUMBER', 'OPERATION', 'ORDERNUMBER', 'AMOUNT',
			'CURRENCY', 'DEPOSITFLAG', 'MERORDERNUM', 'URL', 'DESCRIPTION',
			'MD'
		];

		foreach ($fields as $field)
		{
			if (!empty($signature_base))
			{
				$signature_base .= '|';
			}

			$signature_base .= $this->fields[ $field ];
		}

		return (new PemKeys($this->auth))->sign($signature_base);
	}

	/**
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	public function process($redirect = TRUE)
	{
		$this->validate();

		$this->fields['DIGEST'] = $this->sign();

		$fields = '?';

		foreach ($this->fields as $key => $value)
		{
			$fields .= sprintf('%s=%s&', $key, urlencode($value));
		}

		$redirection = $this->request_server_url().rtrim($fields, '& ');

		if ($redirect === TRUE)
		{
			header('Location: ' . $redirection);
		}

		return $redirection;
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://test.3dsecure.gpwebpay.com/csobsk/order.do'
			: 'https://3dsecure.gpwebpay.com/csobsk/order.do';
	}
}
