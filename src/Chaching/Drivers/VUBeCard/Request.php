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

use \Chaching\Chaching;
use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Base64;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;


class Request extends \Chaching\Message
{
	protected $valid_languages = [ 'sk', 'cz', 'hu', 'en' ];

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [
			'clientid', 'storetype', 'trantype', 'rnd', 'hash'
		];

		$this->required_fields = [ 'oid', 'amount', 'currency', 'okurl' ];
		$this->optional_fields = [ 'failurl', 'lang', 'encoding' ];

		$this->field_map = [
			Driver::VARIABLE_SYMBOL 	=> 'oid',
			Driver::AMOUNT 				=> 'amount',
			Driver::CURRENCY 			=> 'currency',
			Driver::LANGUAGE 			=> 'language',
			Driver::CALLBACK 			=> 'okurl'
		];

		$this->set_authorization($authorization);

		$this->fields['trantype'] 	= 'Auth';
		$this->fields['storetype'] 	= '3d_pay_hosting';
		$this->fields['rnd'] 		= uniqid();

		$this->fields['currency'] 	= Currencies::EUR;
		$this->fields['encoding'] 	= 'utf-8';
		$this->fields['lang'] 		= $this->detect_client_language(
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
		$this->fields['lang'] 	= strtolower($this->fields['lang']);

		if (!is_array($this->auth) OR count($this->auth) !== 2)
			throw new InvalidAuthorizationException(
				"Merchant authorization information is missing."
			);

		$this->fields['clientid'] = isset($this->auth[ 0 ])
			? $this->auth[ 0 ]
			: '';

		if (!preg_match('/^[0-9]+$/', $this->fields['clientid']))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (Client ID) has an " .
				"unacceptable value '%s'. Try changing it to value you " .
				"got from the bank.", $this->fields['clientid']
			));

		if (!isset($this->auth[ 1 ]) OR empty($this->auth[ 1 ]))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the store key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		// Validate all required fields first
		$this->validate_required_fields();

		if (!is_string($this->fields['amount']))
		{
			$this->fields['amount'] = sprintf(
				'%01.2F', $this->fields['amount']
			);
		}

		if (!preg_match('/^[0-9]{1,13}(\.[0-9]{1,2})?$/', $this->fields['amount']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or amount) has an unacceptable value '%s'. Valid " .
				"amount consists of up to 13 base numbers and maximum of two " .
				"decimals separated by a dot ('.').",
				Driver::AMOUNT, $this->fields['amount']
			));

		if (is_string($this->fields['currency']) AND !is_numeric($this->fields['currency']))
		{
			$currency = Currencies::get($this->fields['currency']);

			$this->fields['currency'] = ($currency !== NULL)
				? $currency['alpha_code']
				: NULL;
		}

		if (Currencies::validate_code($this->fields['currency']) === FALSE)
			throw new InvalidOptionsException(sprintf(
				"Field %s (or currency) has an unacceptable value '%s'. " .
				"The easiest way is to use constants provided in " .
				"`\Chaching\Currencies` with currency codes based on ISO 4217.",
				Driver::CURRENCY, $this->fields['currency']
			));

		if (!preg_match('/^[0-9]{1,10}$/', $this->fields['oid']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or oid) has an unacceptable value '%s'. Valid " .
				"order ID consists of up to 10 digits.",
				Driver::VARIABLE_SYMBOL, $this->fields['oid']
			));

		if (!filter_var($this->fields['okurl'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or okurl) has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['okurl']
			));

		if (empty($this->fields['failurl']))
		{
			$this->fields['failurl'] = $this->fields['okurl'];
		}
		else
		{
			if (!filter_var($this->fields['failurl'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
				throw new InvalidOptionsException(sprintf(
					"Field failurl has an unacceptable value '%s'. Valid " .
					"return URL has to be properly formatted.",
					$this->fields['failurl']
				));
		}

		// Optional fields
		if (!in_array($this->fields['lang'], $this->valid_languages))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or lang) has an unacceptable value '%s'. Valid " .
				"language values are '%s'.", Driver::LANGUAGE,
				$this->fields['lang'], implode("', '", $this->valid_languages)
			));
	}

	protected function sign()
	{
		$field_list = [
			'clientid', 'oid', 'amount', 'okurl', 'failurl', 'trantype',
			'rnd', 'storekey'
		];

		$signature_base = '';

		foreach ($field_list as $field)
		{
			$signature_base .= isset($this->fields[ $field ])
				? $this->fields[ $field ]
				: '';
		}

		$signature_base .= $this->auth[ 1 ];

		return (new Base64($this->auth))->sign($signature_base);
	}

	public function process($redirect = TRUE)
	{
		$this->validate();

		$this->fields['hash'] = $this->sign();

		$fields = sprintf(
			"<form action=\"%s\" method=\"post\" id=\"ecard\">\n",
			$this->request_server_url()
		);

		foreach ($this->fields as $key => $value)
		{
			$fields .= sprintf(
				"\t<input type=\"hidden\" name=\"%s\" value=\"%s\">\n",
				$key, $value
			);
		}

		$fields .= "\t<input type=\"submit\" value=\"OK\">\n</form>";
		$fields .= "<script type=\"text/javascript\">\n";
		$fields .= "\tdocument.getElementById('ecard').submit();\n</script>";

		return $fields;
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://testsecurepay.intesasanpaolocard.com/fim/est3dgate'
			: 'https://vub.eway2pay.com/fim/est3dgate';
	}
}
