<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2015 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\PayPal;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Encryption\Base64;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidAuthorizationException;

class Request extends \Chaching\Message
{
	const REQUEST_URI = 'https://www.paypal.com/cgi-bin/webscr';

	protected $valid_languages = array(
		'sk', 'cz', 'hu', 'en'
	);

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'business', 'cmd', 'charset'
		);

		$this->required_fields = array(
			'amount', 'currency_code', 'return', 'description'
		);

		$this->optional_fields = array(
			'no_note', 'no_shipping', 'shipping', 'address_override',
			'cancel_return', 'email', 'first_name', 'last_name', 'address1',
			'zip', 'city', 'country', 'address_override', 'cancel_return',
			'notify_url',
			'cpp_logo_image', 'cpp_cart_border_color'
		);

		$this->field_map = array(
			Driver::VARIABLE_SYMBOL 	=> 'custom',

			Driver::AMOUNT 				=> 'amount',
			Driver::CURRENCY 			=> 'currency_code',

			Driver::LANGUAGE 			=> 'language',

			Driver::CALLBACK 			=> 'return'
		);

		$this->set_authorization($authorization);

		$this->fields['lang'] 			= $this->detect_client_language(
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
		$this->fields['lang'] 	= strtolower($this->fields['lang']);

		$this->fields['business'] = strtolower($this->auth[0]);

		$this->fields['cmd'] = '_xclick';

		if (isset($this->fields['description']))
		{
			$this->fields['item_name'] = $this->fields['description'];
		}

		if (isset($this->fields['address1']) OR isset($this->fields['zip']) OR isset($this->fields['city']) OR isset($this->fields['country']))
		{
			$this->fields['address_override'] = 1;
		}

		$this->fields['no_shipping'] = isset($this->fields['shipping'])
			? 0
			: 1;

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

		/*
		if (is_string($this->fields['currency']) AND !is_numeric($this->fields['currency']))
		{
			$currency = Currencies::get($this->fields['currency']);

			$this->fields['currency'] = ($currency !== NULL)
				? $currency['alpha_code']
				: NULL;
		}

		if (Currencies::validate_code($this->fields['currency']) === NULL)
			throw new InvalidOptionsException(sprintf(
				"Field %s (or currency) has an unacceptable value '%s'. " .
				"The easiest way is to use constants provided in " .
				"`\Chaching\Currencies` with currency codes based on ISO 4217.",
				Driver::CURRENCY, $this->fields['currency']
			));
		*/

		if (!filter_var($this->fields['return'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
			throw new InvalidOptionsException(sprintf(
				"Field return has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['return']
			));

		if (empty($this->fields['cancel_return']))
		{
			$this->fields['cancel_return'] = $this->fields['return'];
		}
		else
		{
			if (!filter_var($this->fields['cancel_return'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
				throw new InvalidOptionsException(sprintf(
					"Field cancel_return has an unacceptable value '%s'. " .
					"Valid return URL has to be properly formatted.",
					$this->fields['cancel_return']
				));
		}

		if (!empty($this->fields['notify_url']))
		{
			if (!filter_var($this->fields['notify_url'], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
				throw new InvalidOptionsException(sprintf(
					"Field notify_url has an unacceptable value '%s'. Valid " .
					"return URL has to be properly formatted.",
					$this->fields['notify_url']
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

	public function process($redirect = TRUE)
	{
		$this->validate();

		$fields = sprintf(
			"<form action=\"%s\" method=\"post\" id=\"paypal\">\n<input type=\"hidden\" name=\"charset\" value=\"utf-8\">\n",
			self::REQUEST_URI
		);

		foreach ($this->fields as $key => $value)
		{
			$fields .= sprintf(
				"\t<input type=\"hidden\" name=\"%s\" value=\"%s\">\n",
				$key, $value
			);
		}

		$fields .= "\t<input type=\"image\" name=\"submit\" border=\"0\" src=\"https://www.paypal.com/en_US/i/btn/btn_buynow_LG.gif\" alt=\"PayPal - The safer, easier way to pay online\"><img alt=\"\" border=\"0\" width=\"1\" height=\"1\" src=\"https://www.paypal.com/en_US/i/scr/pixel.gif\" style=\"outline: none;\">\n</form>";

		$fields .= "<script type=\"text/javascript\">\n";
		$fields .= "\tdocument.getElementById('paypal').submit();\n</script>";

		return $fields;
	}
}
