<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2019 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;


class Currencies
{
	const CHF 	= 756;
	const CZK 	= 203;
	const DKK 	= 208;
	const EUR 	= 978;
	const GBP 	= 826;
	const HUF 	= 348;
	const PLN 	= 985;
	const USD 	= 840;

	private static $currencies = [
		203 	=> [
			'currency' 		=> 'Czech koruna',
			'alpha_code' 	=> 'CZK',
			'numeric_code' 	=> 203,
			'minor_unit' 	=> 2
		],
		208 	=> [
			'currency' 		=> 'Danish krone',
			'alpha_code' 	=> 'DKK',
			'numeric_code' 	=> 208,
			'minor_unit' 	=> 2
		],
		348 	=> [
			'currency' 		=> 'Hungarian forint',
			'alpha_code' 	=> 'HUF',
			'numeric_code' 	=> 348,
			'minor_unit' 	=> 2
		],
		756 	=> [
			'currency' 		=> 'Swiff franc',
			'alpha_code' 	=> 'CHF',
			'numeric_code' 	=> 756,
			'minor_unit' 	=> 2
		],
		826 	=> [
			'currency' 		=> 'Pound sterling',
			'alpha_code' 	=> 'GBP',
			'numeric_code' 	=> 826,
			'minor_unit' 	=> 2
		],
		840 	=> [
			'currency' 		=> 'United States dollar',
			'alpha_code' 	=> 'USD',
			'numeric_code' 	=> 840,
			'minor_unit' 	=> 2
		],
		978 	=> [
			'currency' 		=> 'Euro',
			'alpha_code' 	=> 'EUR',
			'numeric_code' 	=> 978,
			'minor_unit' 	=> 2
		],
		985 	=> [
			'currency' 		=> 'Polish złoty',
			'alpha_code' 	=> 'PLN',
			'numeric_code' 	=> 985,
			'minor_unit' 	=> 2
		],
	];

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
