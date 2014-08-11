<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
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

	protected $authorization = array();

	public function __construct(Array $authorization)
	{
		$this->authorization = $authorization;
	}

	abstract public function request(Array $options);
	abstract public function response(Array $options);
}
