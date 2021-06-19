<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\BenefitPlus;

use \Chaching\Chaching;
use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;


class Request extends \Chaching\Message
{
	protected $valid_languages = [
		'cs-CZ', 'de-AT', 'en-US', 'pl-PL', 'sk-SK'
	];

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [
			'eshop_id'
		];

		$this->required_fields = [
			'amount', 'eshop_culture', 'order_num', 'partialPayment', 'return_url'
		];

		$this->optional_fields = [
			'description'
		];

		$this->field_map = [
			Driver::AMOUNT 				=> 'amount',
			Driver::DESCRIPTION 		=> 'description',
			Driver::VARIABLE_SYMBOL 	=> 'order_num',
			Driver::LANGUAGE 			=> 'eshop_culture',
			Driver::CALLBACK 			=> 'return_url'
		];

		$this->set_authorization($authorization);

		$this->fields['partialPayment'] = 0;
		$this->fields['eshop_culture'] = 'sk-SK';

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
		if (!is_array($this->auth) OR empty($this->auth[ 0 ]))
			throw new InvalidAuthorizationException("Eshop ID is missing.");

		$this->fields['eshop_id'] = isset($this->auth[ 0 ])
			? $this->auth[ 0 ]
			: '';

		if (!preg_match('/^[a-zA-Z0-9]{8}\-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/', $this->fields['eshop_id']))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (Eshop ID) has an " .
				"unacceptable value '%s'. Try changing it to value you " .
				"got from Benefit Plus.", $this->fields['eshop_id']
			));

		$this->fields['partialPayment'] = (int) $this->fields['partialPayment'];

		if (!in_array($this->fields['partialPayment'], [ 0, 1 ]))
			throw new InvalidOptionsException(sprintf(
				"Field partialPayment has an unacceptable value '%s'. Valid value " .
				"would be either integer 0 or 1.", $this->fields['partialPayment']
			));

		if (!in_array($this->fields['eshop_culture'], $this->valid_languages))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or eshop_culture) has an unacceptable value '%s'. Valid " .
				"language values are '%s'.", Driver::LANGUAGE,
				$this->fields['eshop_culture'], implode("', '", $this->valid_languages)
			));

		// Validate all required fields first
		$this->validate_required_fields();

		if (!is_string($this->fields['amount']))
		{
			$this->fields['amount'] = sprintf('%01.2F', $this->fields['amount']);
		}

		if (!filter_var($this->fields['return_url'], FILTER_VALIDATE_URL))
			throw new InvalidOptionsException(sprintf(
				"Field %s (or RURL) has an unacceptable value '%s'. Valid " .
				"return URL has to be properly formatted.", Driver::CALLBACK,
				$this->fields['return_url']
			));

		// Optional fields
		if (isset($this->fields['description']) AND !empty($this->fields['description']))
		{
			if (!preg_match('/[a-zA-Z0-9 \.,\_-]{1,20}/', $this->fields['description']))
				throw new InvalidOptionsException(sprintf(
					"Field %s (or description) has an unacceptable value '%s'. " .
					"Valid description can not contain any accents or fancy " .
					"characters.", Driver::DESCRIPTION, $this->fields['description']
				));
		}
	}

	/**
	 * @throw 	\Chaching\Exceptions\InvalidRequestException
	 */
	public function process($redirect = TRUE)
	{
		$this->validate();

		$fields = sprintf(
			"<form action=\"%s\" method=\"post\" id=\"benefitplus\">\n",
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
		$fields .= "\tdocument.getElementById('benefitplus').submit();\n</script>";

		return $fields;
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://benefitv3.sprinx.cz/Pages/PayGateDefault.aspx'
			: 'https://inside.benefit-plus.eu/Pages/PayGateDefault.aspx';
	}
}
