<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2020 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TrustPay;

use \Chaching\Chaching;
use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;


class Request extends \Chaching\Message
{
	private $valid_languages = [
		'bg', 'bs', 'cz', 'en', 'es', 'et', 'hr', 'hu', 'it', 'lt', 'lv',
		'pl', 'ro', 'ru', 'sk', 'sl', 'sr', 'tr', 'uk'
	];

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'AID', 'SIG' ];
		$this->required_fields = [ 'AMT', 'CUR' ];

		$this->optional_fields = [
			'REF', 'URL', 'RURL', 'CURL', 'EURL', 'NURL', 'LNG', 'CNT',
			'DSC', 'EMA'
		];

		$this->field_map = [
			Driver::AMOUNT 				=> 'AMT',
			Driver::CURRENCY 			=> 'CUR',
			Driver::DESCRIPTION 		=> 'DSC',
			Driver::REFERENCE_NUMBER 	=> 'REF',
			Driver::CLIENT_EMAIL 		=> 'EMA',
			Driver::CLIENT_COUNTRY 		=> 'CNT',
			Driver::LANGUAGE 			=> 'LNG',
			Driver::CALLBACK 			=> 'URL'
		];

		$this->set_authorization($authorization);

		$this->fields['CUR'] 			= Currencies::EUR;
		$this->fields['LNG'] 			= $this->detect_client_language(
			$this->valid_languages
		);

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
		$this->fields['LNG'] = strtolower($this->fields['LNG']);

		if (!is_array($this->auth) OR count($this->auth) !== 2)
			throw new InvalidAuthorizationException(
				"Merchant authorization information is missing."
			);

		$this->fields['AID'] = isset($this->auth[ 0 ])
			? $this->auth[ 0 ]
			: '';

		if (!preg_match('/^[a-zA-Z0-9]{1,10}$/', $this->fields['AID']))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (Merchant ID or AID) has an " .
				"unacceptable value '%s'. Try changing it to value you " .
				"got from the bank.", $this->fields['AID']
			));

		if (!isset($this->auth[ 1 ]) OR empty($this->auth[ 1 ]))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the secret key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		// Validate all required fields first
		$this->validate_required_fields();

		if (!is_string($this->fields['AMT']))
		{
			$this->fields['AMT'] = sprintf('%01.2F', $this->fields['AMT']);
		}

		if (!preg_match('/^[0-9]{1,13}(\.[0-9]{1,2})?$/', $this->fields['AMT']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or AMT) has an unacceptable value '%s'. Valid " .
				"amount consists of up to 13 base numbers and maximum of two " .
				"decimals separated by a dot ('.').",
				Driver::AMOUNT, $this->fields['AMT']
			));

		if (isset($this->fields['REF']))
		{
			if (!preg_match('/^[0-9a-zA-Z]{1,19}$/', $this->fields['REF']))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or REF) has an unacceptable value '%s'. Valid " .
					"reference number consists of up to 19 alphanumeric characters.",
					Driver::REFERENCE_NUMBER, $this->fields['REF']
				));
		}

		// Optional fields
		if (isset($this->fields['URL']))
		{
			if (!filter_var($this->fields['URL'], FILTER_VALIDATE_URL))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or URL) has an unacceptable value '%s'. Valid " .
					"return URL has to be properly formatted.",
					Driver::CALLBACK, $this->fields['URL']
				));
		}

		if (isset($this->fields['RURL']))
		{
			if (!filter_var($this->fields['RURL'], FILTER_VALIDATE_URL))
				throw new InvalidOptionsException(sprintf(
					"Field RURL has an unacceptable value '%s'. Valid " .
					"return URL has to be properly formatted.",
					$this->fields['RURL']
				));
		}

		if (isset($this->fields['CURL']))
		{
			if (!filter_var($this->fields['CURL'], FILTER_VALIDATE_URL))
				throw new InvalidOptionsException(sprintf(
					"Field CURL has an unacceptable value '%s'. Valid " .
					"return URL has to be properly formatted.",
					$this->fields['CURL']
				));
		}

		if (isset($this->fields['EURL']))
		{
		if (!filter_var($this->fields['EURL'], FILTER_VALIDATE_URL))
			throw new InvalidOptionsException(sprintf(
				"Field EURL has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.",
				$this->fields['EURL']
			));
		}

		if (isset($this->fields['NURL']))
		{
			if (!filter_var($this->fields['NURL'], FILTER_VALIDATE_URL))
				throw new InvalidOptionsException(sprintf(
					"Field NURL has an unacceptable value '%s'. Valid " .
					"return URL has to be properly formatted.",
					$this->fields['NURL']
				));
		}

		if (isset($this->fields['EMA']))
		{
			if (!filter_var($this->fields['EMA'], FILTER_VALIDATE_EMAIL))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or EMA) has an unacceptable value '%s'. Valid " .
					"return email address has to be properly formatted.",
					Driver::CLIENT_EMAIL, $this->fields['EMA']
				));
		}

		if (isset($this->fields['CUR']) AND !empty($this->fields['CUR']))
		{
			if (($currency = Currencies::get($this->fields['CUR'])) === NULL)
				throw new InvalidOptionsException(sprintf(
					"Field %s (or CUR) has an unacceptable value '%s'. " .
					"The easiest way is to use constants provided in " .
					"`\Chaching\Currencies` with currency codes based " .
					"on ISO 4217.",
					Driver::CURRENCY, $this->fields['CUR']
				));

			$this->fields['CUR'] = $currency['alpha_code'];
		}

		if (isset($this->fields['DSC']) AND !empty($this->fields['DSC']))
		{
			if (!preg_match('/[a-zA-Z0-9 \.,\_-]{1,256}/', $this->fields['DSC']))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or DESC) has an unacceptable value '%s'. " .
					"Valid description can not contain any accents or fancy " .
					"characters.", Driver::DESCRIPTION, $this->fields['DESC']
				));
		}

		if (!in_array($this->fields['LNG'], $this->valid_languages))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or LNG) has an unacceptable value '%s'. Valid " .
				"language values are '%s'.", Driver::LANGUAGE,
				$this->fields['LNG'], implode("', '", $this->valid_languages)
			));
	}

	protected function sign()
	{
		$signature_base = $this->fields['AID'] . $this->fields['AMT'] .
			$this->fields['CUR'] . $this->fields['REF'];

		return (new Hmac($this->auth))->sign($signature_base);
	}

	/**
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	public function process($redirect = TRUE)
	{
		$this->validate();

		$this->fields['SIGN'] = $this->sign();

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
		else
		{
			return $redirection;
		}
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://ib.test.trustpay.eu/mapi/pay.aspx'
			: 'https://ib.trustpay.eu/mapi/pay.aspx';
	}
}
