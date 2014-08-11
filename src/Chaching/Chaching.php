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

use \Chaching\Exceptions\InvalidOptionsException;

class Chaching
{
	const CARDPAY 	= 'cardpay';
	const TATRAPAY 	= 'tatrapay';
	const TRUSTPAY 	= 'trustpay';
	const EPLATBY 	= 'eplatby';

	private $payment_drivers = array(
		self::CARDPAY 	=> 'TBCardPay',
		self::TATRAPAY 	=> 'TBTatraPay',
		self::TRUSTPAY 	=> 'TrustPay',
		self::EPLATBY 	=> 'VUBEplatby'
	);

	/**
	 * Vytvara objekt na pracu s platbami cez specifikovany radic.
	 *
	 * @param 	int 	$driver 		kod radica banky, cez ktory ma byt
	 * 									vykonavana platba
	 * @param 	array 	$authorization 	pole s autentifikacnymi prvkami 
	 * 									na prihlasenie do banky a podpis
	 * 									poziadavky
	 **/
	public function __construct($driver, Array $authorization)
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

		$this->driver = new $driver($authorization);
	}

	public function __call($method, $arguments)
	{
		if (method_exists($this->driver, $method))
			return call_user_func_array(
				array($this->driver, $method), $arguments
			);

		throw new \BadMethodCallException(sprintf(
			"Method %s not implemented in driver", $method
		));
	}

	public function request($options)
	{
		return $this->driver->request((array) $options);
	}

	public function response($options)
	{
		return $this->driver->response((array) $options);
	}
}
