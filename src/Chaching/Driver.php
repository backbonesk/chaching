<?php
namespace Chaching;

abstract class Driver
{
	const AMOUNT 			= 'amount';
	const CURRENCY 			= 'currency';
	const DESCRIPTION 		= 'description';
	const VARIABLE_SYMBOL 	= 'variable_symbol';
	const CONSTANT_SYMBOL 	= 'constant_symbol';
	const SPECIFIC_SYMBOL 	= 'specific_symbol';
	const CLIENT_NAME 		= 'name';
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
