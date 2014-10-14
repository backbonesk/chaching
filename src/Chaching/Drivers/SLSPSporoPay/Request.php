<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\SLSPSporoPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;

final class Request extends \Chaching\Messages\TripleDes
{
	const REQUEST_URI = 'https://ib.slsp.sk/epayment/epayment/epayment.xml';

	private $valid_languages = array(
		'sk', 'en', 'de'
	);

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'pu_predcislo', 'pu_cislo', 'pu_kbanky', 'sign1'
		);

		$this->required_fields = array(
			'param', 'suma', 'mena', 'vs', 'ss', 'url'
		);

		$this->optional_fields = array(
			'mail_notif_att', 'email_adr'
		);

		$this->field_map = array(
			Driver::PREFIX 				=> 'pu_predcislo',
			Driver::ACCOUNT_NO 			=> 'pu_cislo',
			Driver::BANK_CODE 			=> 'pu_kbanky',

			Driver::AMOUNT 				=> 'suma',
			Driver::CURRENCY 			=> 'mena',
			Driver::DESCRIPTION 		=> 'param',
			Driver::VARIABLE_SYMBOL 	=> 'vs',
			Driver::SPECIFIC_SYMBOL 	=> 'ss',
 
			Driver::CALLBACK 			=> 'url',
			Driver::RETURN_EMAIL 		=> 'email_adr'
		);

		$this->set_authorization($authorization);

		if (($currency = Currencies::get(Currencies::EUR)) !== NULL)
		{
			$this->fields['mena'] 		= $currency['alpha_code'];
		}

		if (is_array($options) AND !empty($options))
		{
			$this->set_options($options);
		}
	}

	public function set_options(Array $options)
	{
		foreach ($options as $option => $value)
		{
			$this->$option = $value;
		}
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	protected function validate()
	{
		if (!is_array($this->auth) OR count($this->auth) !== 4 OR !isset($this->auth['prefix']) OR !isset($this->auth['shared_secret']))
			throw new \Chaching\Exceptions\InvalidRequestException(
				"Merchant authorization information is missing."
			);

		if (!isset($this->auth['account_no']) OR empty($this->auth['account_no']))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (account_no) is missing " .
				"value. Try changing it to value you got from the bank",
				$this->fields['MID']
			));

		$this->fields['pu_cislo'] 		= $this->auth['account_no'];
		$this->fields['pu_predcislo'] 	= isset($this->auth['prefix'])
			? $this->auth['prefix']
			: '000000';
		$this->fields['pu_kbanky'] 		= isset($this->auth['bank_code'])
			? $this->auth['bank_code']
			: '0900';

		if (!isset($this->auth['shared_secret']) OR empty($this->auth['shared_secret']))
			throw new InvalidOptionsException(
				"Authorization information are unacceptable as it does " .
				"not include the shared key to sign requests. Try " .
				"changing it to value you got from the bank."
			);

		if (!isset($this->fields['ss']) OR empty($this->fields['ss']))
		{
			$this->fields['ss'] 		= 1000000000;
		}

		if (!isset($this->fields['param']) OR empty($this->fields['param']))
		{
			$this->fields['param'] 		= 'pay=1';
		}

		// Validate all required fields first
		$this->validate_required_fields();

		if (!is_string($this->fields['suma']))
		{
			$this->fields['suma'] = sprintf('%01.2F', $this->fields['suma']);
		}

		if (!preg_match('/^[0-9]{1,13}(\.[0-9]{1,2})?$/', $this->fields['suma']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or `suma`) has an unacceptable value '%s'. Valid " .
				"amount consists of up to 13 base numbers and maximum of two " .
				"decimals separated with a dot ('.').",
				Driver::AMOUNT, $this->fields['suma']
			));

		if (is_numeric($this->fields['mena']))
		{
			$currency = Currencies::get((int) $this->fields['mena']);

			$this->fields['mena'] = ($currency !== NULL)
				? $currency['alpha_code']
				: NULL;
		}

		if (Currencies::validate_code($this->fields['mena']) === NULL)
			throw new InvalidOptionsException(sprintf(
				"Field %s (or `mena`) has an unacceptable value '%s'. " .
				"The easiest way is to use constants provided in " . 
				"`\Chaching\Currencies` with currency codes based on ISO 4217.",
				Driver::CURRENCY, $this->fields['mena']
			));

		if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $this->fields['vs']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or `vs`) has an unacceptable value '%s'. Valid " .
				"variable symbol consists of up to 20 alphanumeric characters.",
				Driver::VARIABLE_SYMBOL, $this->fields['vs']
			));

		if (!filter_var($this->fields['url'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or `url`) has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['url']
			));

		if (!preg_match('/^[0-9]{1,20}$/', $this->fields['ss']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or `ss`) has an unacceptable value '%s'. Valid " .
				"specific symbol consists of up to 10 digits.",
				Driver::SPECIFIC_SYMBOL, $this->fields['vs']
			));

		// Optional fields
		if (isset($this->fields['email_adr']))
		{
			if (!filter_var($this->fields['email_adr'], FILTER_VALIDATE_EMAIL))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or `email_adr`) has an unacceptable value " .
					"'%s'. Valid return email address has to be properly " . 					"formatted.",
					Driver::RETURN_EMAIL, $this->fields['email_adr']
				));
		}
	}

	protected function signature_base()
	{
		$field_list 		= [
			'pu_predcislo', 'pu_cislo', 'pu_kbanky', 'suma', 'mena',
			'vs', 'ss', 'url', 'param'
		];

		$signature_base 	= '';

		foreach ($field_list as $field)
		{
			if (!empty($signature_base))
			{
				$signature_base .= ';';
			}

			$signature_base .= isset($this->fields[ $field ])
				? $this->fields[ $field ]
				: '';
		}

		return $signature_base;
	}

	/**
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	public function process($redirect = TRUE)
	{
		$this->validate();

		$this->fields['sign1'] = $this->sign($this->signature_base());

		$fields = '?';

		foreach ($this->fields as $key => $value)
		{
			$fields .= sprintf('%s=%s&', $key, urlencode($value));
		}

		$redirection = self::REQUEST_URI.rtrim($fields, '& ');

		if ($redirect === TRUE)
		{
			header('Location: ' . $redirection);
		}
		else
		{
			return $redirection;
		}
	}
}
