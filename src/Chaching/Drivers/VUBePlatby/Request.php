<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2018 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\VUBePlatby;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;


class Request extends \Chaching\Message
{
	const REQUEST_URI = 'https://ib.vub.sk/e-platbyeuro.aspx';

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'MID', 'SIGN' ];
		$this->required_fields = [ 'AMT', 'VS', 'CS', 'RURL' ];
		$this->optional_fields = [ 'DESC', 'SS', 'RSMS', 'REM' ];

		$this->field_map = [
			Driver::AMOUNT 				=> 'AMT',
			Driver::DESCRIPTION 		=> 'DESC',
			Driver::VARIABLE_SYMBOL 	=> 'VS',
			Driver::CONSTANT_SYMBOL 	=> 'CS',
			Driver::SPECIFIC_SYMBOL 	=> 'SS',
			Driver::CALLBACK 			=> 'RURL',
			Driver::RETURN_PHONE 		=> 'RSMS',
			Driver::RETURN_EMAIL 		=> 'REM'
		];

		$this->set_authorization($authorization);

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
				"Merchant authorization information is missing."
			);

		$this->fields['MID'] = isset($this->auth[ 0 ])
			? $this->auth[ 0 ]
			: '';

		if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $this->fields['MID']))
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

		if (!preg_match('/^[0-9]{1,13}(\.[0-9]{1,2})?$/', $this->fields['AMT']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or AMT) has an unacceptable value '%s'. Valid " .
				"amount consists of up to 13 base numbers and maximum of two " .
				"decimals separated by a dot ('.').",
				Driver::AMOUNT, $this->fields['AMT']
			));

		if (!preg_match('/^[0-9]{1,10}$/', $this->fields['VS']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or VS) has an unacceptable value '%s'. Valid " .
				"variable symbol consists of up to 10 digits.",
				Driver::VARIABLE_SYMBOL, $this->fields['VS']
			));

		if (!filter_var($this->fields['RURL'], FILTER_VALIDATE_URL))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or RURL) has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['RURL']
			));

		if (!preg_match('/^[0-9]{1,4}$/', $this->fields['CS']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or CS) has an unacceptable value '%s'. Valid " .
				"constant symbol consists of up to 4 numbers.",
				Driver::CONSTANT_SYMBOL, $this->fields['VS']
			));


		// Optional fields
		if (isset($this->fields['SS']) AND !empty($this->fields['SS']) AND !preg_match('/^[0-9]{1,10}$/', $this->fields['SS']))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or SS) has an unacceptable value '%s'. Valid " .
				"specific symbol consists of up to 10 digits.",
				Driver::SPECIFIC_SYMBOL, $this->fields['SS']
			));

		if (isset($this->fields['RSMS']))
		{
			$phone = $this->format_mobile_number($this->fields['RSMS']);

			if ($phone === NULL)
				throw new InvalidOptionsException(sprintf(
					"Field %s (or RSMS) has an unacceptable value '%s'. ",
					Driver::RETURN_PHONE, $this->fields['RSMS']
				));

			$this->fields['RSMS'] = str_replace('+421', '0', $phone);
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
	}

	protected function sign()
	{
		$signature_base =
			$this->fields['MID'] . $this->fields['AMT'] . $this->fields['VS'] .
			(isset($this->fields['SS']) ? $this->fields['SS'] : '') .
			$this->fields['CS'] . $this->fields['RURL'];

		return (new Hmac($this->auth))->sign($signature_base);
	}

	/**
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	public function process($redirect = TRUE)
	{
		$this->validate();

		if (($this->fields['SIGN'] = $this->sign()) === NULL)
			throw new \Chaching\Exceptions\InvalidRequestException(
				"Merchant authorization information (shared secret) is invalid."
			);

		$fields = sprintf(
			"<form action=\"%s\" method=\"post\" id=\"eplatby\">\n",
			self::REQUEST_URI
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
		$fields .= "\tdocument.getElementById('eplatby').submit();\n</script>";

		return $fields;
	}
}
