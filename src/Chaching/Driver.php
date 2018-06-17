<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2018 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;


abstract class Driver
{
	const AMOUNT 			= 'amount';
	const CURRENCY 			= 'currency';
	const DESCRIPTION 		= 'description';
	const VARIABLE_SYMBOL 	= 'variable_symbol';
	const CONSTANT_SYMBOL 	= 'constant_symbol';
	const SPECIFIC_SYMBOL 	= 'specific_symbol';
	const REFERENCE_NUMBER 	= 'reference_number';
	const CLIENT_NAME 		= 'name';
	const CLIENT_EMAIL 		= 'email';
	const CLIENT_COUNTRY 	= 'country';
	const CLIENT_IP 		= 'client_ip';
	const LANGUAGE 			= 'language';
	const CALLBACK 			= 'callback';
	const RETURN_PHONE 		= 'return_phone';
	const RETURN_EMAIL 		= 'return_email';
	const PREFIX 			= 'account_prefix';
	const ACCOUNT_NO 		= 'account_no';
	const BANK_CODE 		= 'bank_code';
	const CARD_ID 			= 'card_id';
	const NOTIFICATION_URL 	= 'notification_url';
	const TRANSACTION_ID 	= 'transaction_id';

	protected $authorization 	= [];
	protected $options 			= [];

	public function __construct(Array $authorization, Array $options = [])
	{
		$this->authorization 	= $authorization;
		$this->options 			= $options;
	}

	abstract public function request(Array $attributes);
	abstract public function response(Array $attributes);
}
