<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;

use \Chaching\Exceptions\InvalidOptionsException;


class Chaching
{
	const VERSION 		= '0.23.1';

	const CARDPAY 		= 'cardpay';
	const SPOROPAY 		= 'sporopay';
	const TATRAPAY 		= 'tatrapay';
	const TRUSTPAY 		= 'trustpay';
	const EPLATBY 		= 'eplatby';
	const ECARD 		= 'ecard';
	const PAYPAL		= 'paypal';
	const GPWEBPAY 		= 'gpwebpay';
	const ITERMINAL 	= 'iterminal';
	const ITERMINAL2 	= 'iterminal2';
	const BENEFITPLUS 	= 'benefitplus';

	const PRODUCTION 	= 'production';
	const SANDBOX 		= 'sandbox';

	private $payment_drivers = [
		self::SPOROPAY 		=> 'SLSPSporoPay',
		self::CARDPAY 		=> 'TBCardPay',
		self::TATRAPAY 		=> 'TBTatraPay',
		self::TRUSTPAY 		=> 'TrustPay',
		self::EPLATBY 		=> 'VUBePlatby',
		self::ECARD 		=> 'VUBeCard',
		self::PAYPAL		=> 'PayPal',
		self::GPWEBPAY 		=> 'GPwebpay',
		self::ITERMINAL 	=> 'PBiTerminal',
		self::ITERMINAL2 	=> 'PBiTerminal2',
		self::BENEFITPLUS 	=> 'BenefitPlus'
	];

	/**
	 * Create object to work with payments via specified driver.
	 *
	 * @param 	int 		$driver 		driver handle
	 * @param 	array 		$authorization 	basic authentication information
	 * 										to service
	 * @param 	array|NULL 	$additional_information 	additional information
	 * 													to service
	 **/
	public function __construct($driver, Array $authorization, Array $options = [])
	{
		if (!is_string($driver) OR !isset($this->payment_drivers[ $driver ]))
			throw new InvalidOptionsException(sprintf(
				"Invalid driver '%s' in use. Valid drivers are '%s'.",
				$driver, implode("', '", array_keys($this->payment_drivers))
			));

		$driver = '\\Chaching\\Drivers\\' . $this->payment_drivers[ $driver ];

		if (!class_exists($driver))
			throw new InvalidOptionsException(sprintf(
				"[internal] Requested driver '%s' does not appear to have " .
				"class definition associated.",
				$driver, implode("', '", array_keys($this->payment_drivers))
			));

		$this->driver = new $driver($authorization, $options);
	}

	public function __call($method, $arguments)
	{
		if (method_exists($this->driver, $method))
			return call_user_func_array(
				[ $this->driver, $method ], $arguments
			);

		throw new \BadMethodCallException(sprintf(
			"Method %s not implemented in driver", $method
		));
	}

	public function request($attributes)
	{
		return $this->driver->request((array) $attributes);
	}

	public function response($attributes)
	{
		return $this->driver->response((array) $attributes);
	}
}
