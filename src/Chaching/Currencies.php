<?php
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

	public static function validate_numeric_code($numeric_code)
	{
		if (!is_numeric($numeric_code))
			return FALSE;

		return (bool) isset(self::$currencies[ (int) $numeric_code ]);
	}
}
