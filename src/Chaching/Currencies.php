<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2015 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;

class Currencies
{
	const EUR 	= 978;

	private static $currencies = array(
		978 	=> array(
			'currency' 		=> 'Euro',
			'alpha_code' 	=> 'EUR',
			'numeric_code' 	=> 978,
			'minor_unit' 	=> 2
		)
	);

	public static function get($code)
	{
		if (is_numeric($code))
			return isset(self::$currencies[ (int) $code ])
				? self::$currencies[ (int) $code ]
				: NULL;

		foreach (self::$currencies as $numeric_code => $data)
		{
			if (isset($data['alpha_code']) AND $data['alpha_code'] == $code)
				return $data;
		}

		return NULL;
	}

	public static function validate_code($code)
	{
		return (bool) (self::get($code) !== NULL);
	}
}
