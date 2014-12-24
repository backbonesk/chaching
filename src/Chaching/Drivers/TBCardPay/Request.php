<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TBCardPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidAuthorizationException;

class Request extends \Chaching\Messages\Des
{
	const REQUEST_URI = 'https://moja.tatrabanka.sk/cgi-bin/e-commerce/start/e-commerce.jsp';

	protected $valid_languages = array(
		'sk', 'en', 'de', 'hu', 'cz', 'es', 'fr', 'it', 'pl'
	);

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'PT', 'MID', 'MOBILE_DEVICE', 'SIGN'
		);

		$this->required_fields = array(
			'AMT', 'CURR', 'VS', 'RURL', 'IPC', 'NAME', 'TPAY'
		);

		$this->optional_fields = array(
			'CS', 'RSMS', 'REM', 'DESC', 'AREDIR', 'LANG', 'TXN',
			'CID', 'TEM', 'TSMS'
		);

		$this->field_map = array(
			Driver::AMOUNT 				=> 'AMT',
			Driver::CURRENCY 			=> 'CURR',
			Driver::DESCRIPTION 		=> 'DESC',
			Driver::VARIABLE_SYMBOL 	=> 'VS',
			Driver::CONSTANT_SYMBOL 	=> 'CS',

			Driver::CLIENT_NAME 		=> 'NAME',
			Driver::CLIENT_IP 			=> 'IPC',
			Driver::LANGUAGE 			=> 'LANG',

			Driver::CALLBACK 			=> 'RURL',
			Driver::RETURN_PHONE 		=> 'RSMS',
			Driver::RETURN_EMAIL 		=> 'REM',

			Driver::CARD_ID 			=> 'CID'
		);

		$this->set_authorization($authorization);

		$this->fields['PT'] 			= 'CardPay';
		$this->fields['TPAY'] 			= 'N';
		$this->fields['AREDIR'] 		= '1';

		$this->fields['CURR'] 			= \Chaching\Currencies::EUR;
		$this->fields['IPC'] 			= isset($_SERVER['REMOTE_ADDR'])
			? $_SERVER['REMOTE_ADDR']
			: $_SERVER['SERVER_ADDR'];

		$this->fields['MOBILE_DEVICE'] 	= (int) $this->detect_mobile_request();
		$this->fields['LANG'] 			= $this->detect_client_language(
			$this->valid_languages
		);

		if (is_array($options) AND !empty($options))
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
		$this->fields['LANG'] 	= strtolower($this->fields['LANG']);
		$this->fields['NAME'] 	= $this->deaccentize($this->fields['NAME']);

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

		if (is_string($this->fields['CURR']) AND !is_numeric($this->fields['CURR']))
		{
			$currency = Currencies::get($this->fields['CURR']);

			$this->fields['CURR'] = ($currency !== NULL)
				? $currency['numeric_code']
				: NULL;
		}

		if (Currencies::validate_code($this->fields['CURR']) === NULL)
			throw new InvalidOptionsException(sprintf(
				"Field %s (or CURR) has an unacceptable value '%s'. " .
				"The easiest way is to use constants provided in " .
				"`\Chaching\Currencies` with currency codes based on ISO 4217.",
				Driver::CURRENCY, $this->fields['CURR']
			));

		if (!preg_match('/^[0-9]{1,10}$/', $this->fields['VS']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or VS) has an unacceptable value '%s'. Valid " .
				"variable symbol consists of up to 10 digits.",
				Driver::VARIABLE_SYMBOL, $this->fields['VS']
			));

		if (!filter_var($this->fields['RURL'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or RURL) has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['RURL']
			));

		$url_restricted_characters = array('&', '?', ';', '=', '+', '%');

		foreach ($url_restricted_characters as $char)
		{
			if (strpos($this->fields['RURL'], $char) !== FALSE)
				throw new InvalidOptionsException(sprintf(
					"Field %s (or RURL) contains unacceptable character " .
					"'%s'. Valid return URL can not contain query string " .
					"characters.", Driver::CALLBACK, $char
				));
		}

		if (!filter_var($this->fields['IPC'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_RES_RANGE))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or IPC) has an unacceptable value '%s'. Valid " .
				"client IP address has to be a properly formatted IPv4.",
				Driver::CLIENT_IP, $this->fields['IPC']
			));

		if (strlen($this->fields['NAME']) > 30)
		{
			$this->fields['NAME'] = substr($this->fields['NAME'], 0, 29);
		}

		if (!preg_match('/^[0-9a-zA-Z \.-\_@]{1,30}/', $this->fields['NAME']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or NAME) has an unacceptable value '%s'. Valid " .
				"client name can not contain any accents or fancy characters.",
				Driver::CLIENT_NAME, $this->fields['NAME']
			));

		// Optional fields
		if ($this->fields['PT'] !== 'CardPay')
		{
			$this->fields['PT'] = 'CardPay';
		}

		if (isset($this->fields['CS']) AND !preg_match('/^[0-9]{1,4}$/', $this->fields['CS']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or CS) has an unacceptable value '%s'. Valid " .
				"constant symbol consists of up to 4 numbers.",
				Driver::CONSTANT_SYMBOL, $this->fields['VS']
			));

		if (isset($this->fields['RSMS']))
		{
			$phone = $this->format_mobile_number($this->fields['RSMS']);

			if ($phone === NULL)
				throw new InvalidOptionsException(sprintf(
					"Field %s (or RSMS) has an unacceptable value '%s'. ",
					Driver::RETURN_PHONE, $this->fields['RSMS']
				));

			$this->fields['RSMS'] = $phone;
		}

		if (isset($this->fields['REM']))
		{
			if (!filter_var($this->fields['REM'], FILTER_VALIDATE_EMAIL))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or REM) has an unacceptable value '%s'. Valid " .
					"return email address has to be properly formatted.",
					Driver::RETURN_EMAIL, $this->fields['REM']
				));
		}

		if (isset($this->fields['DESC']) AND !empty($this->fields['DESC']))
		{
			if (!preg_match('/[a-zA-Z0-9 \.,\_-]{1,20}/', $this->fields['DESC']))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or DESC) has an unacceptable value '%s'. " .
					"Valid description can not contain any accents or fancy " .
					"characters.", Driver::DESCRIPTION, $this->fields['DESC']
				));
		}

		if ($this->fields['AREDIR'] === 0 OR $this->fields['AREDIR'] === 1)
			throw new InvalidOptionsException(sprintf(
				"Field AREDIR has an unacceptable value '%s'. Valid value " .
				"would be either integer 0 or 1.", $this->fields['AREDIR']
			));

		if (!in_array($this->fields['LANG'], $this->valid_languages))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or LANG) has an unacceptable value '%s'. Valid " .
				"language values are '%s'.", Driver::LANGUAGE,
				$this->fields['LANG'], implode("', '", $this->valid_languages)
			));

		if (isset($this->fields['TPAY']) AND $this->fields['TPAY'] === 'Y')
		{
			unset($this->fields['CS']);

			if (isset($this->fields['CID']))
			{
				$this->fields['CID'] = (int) $this->fields['CID'];

				if (strlen($this->fields['CID']) > 19)
					throw new InvalidOptionsException(sprintf(
						"Field %s has an unacceptable value '%s'. ",
						$this->fields['TSMS']
					));
			}

			if (isset($this->fields['TSMS']))
			{
				$phone = $this->format_mobile_number($this->fields['TSMS']);

				if ($phone === NULL)
					throw new InvalidOptionsException(sprintf(
						"Field %s has an unacceptable value '%s'. ",
						$this->fields['TSMS']
					));

				$this->fields['TSMS'] = $phone;
			}

			if (isset($this->fields['TEM']))
			{
				if (!filter_var($this->fields['TEM'], FILTER_VALIDATE_EMAIL))
					throw new InvalidOptionsException(sprintf(
						"Field TEM has an unacceptable value '%s'. Valid " .
						"return email address has to be properly formatted.",
						$this->fields['TEM']
					));
			}
		}
	}

	protected function signature_base()
	{
		$field_list = [
			'MID', 'AMT', 'CURR', 'VS', 'CS', 'RURL', 'IPC', 'NAME'
		];

		if (isset($this->fields['TPAY']) AND $this->fields['TPAY'] === 'Y')
		{
			// ComfortPat doesn't use constant symbols.
			unset($field_list[ 4 ]);

			$field_list = array_merge($field_list, [ 'TPAY', 'CID' ]);
		}

		$signature_base = '';

		foreach ($field_list as $field)
		{
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

		$this->fields['SIGN'] = $this->sign($this->signature_base());

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
