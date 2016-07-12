<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;

use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidRequestException;


abstract class Message
{
	protected $auth 			= [];

	protected $fields 			= [];
	protected $field_map 		= [];

	protected $readonly_fields 	= [];
	protected $required_fields 	= [];
	protected $optional_fields 	= [];

	private $client_user_agent 	= '';

	public function __construct()
	{
		$this->client_user_agent = isset($_SERVER['HTTP_USER_AGENT'])
			? $_SERVER['HTTP_USER_AGENT']
			: '';
	}

	public function __get($name)
	{
		if (in_array($name, array_keys($this->fields)))
			return $this->fields[ $name ];

		if (in_array($name, array_keys($this->field_map)))
			return isset($this->fields[ $this->field_map[ $name ] ])
				? $this->fields[ $this->field_map[ $name ] ]
				: NULL;

		throw new InvalidOptionsException(sprintf(
			"Trying to get field '%s'.", $name
		));
	}

	public function __isset($name)
	{
		if (in_array($name, array_keys($this->fields)))
			return TRUE;

		if (in_array($name, array_keys($this->field_map)))
			return (bool) isset($this->fields[ $this->field_map[ $name ] ]);

		return FALSE;
	}

	public function __set($name, $value)
	{
		if (in_array($name, $this->readonly_fields))
			throw new InvalidOptionsException(sprintf(
				"Trying to set read-only field '%s'.", $name
			));

		$all_field_names = array_merge(
			$this->required_fields, $this->optional_fields
		);

		if (in_array($name, $all_field_names))
		{
			$this->fields[ $name ] = $value;
		}
		else
		{
			if (!in_array($name, array_keys($this->field_map)))
				return;

			$this->fields[ $this->field_map[ $name ] ] = $value;
		}
	}

	public function set_options(Array $options)
	{
		foreach ($options as $option => $value)
		{
			$this->$option = $value;
		}
	}

	/**
	 * Remove common accents from given string.
	 */
	protected function deaccentize($string)
	{
		$return = '';

		$string = str_replace([
			' - ', ' ', '-', 'á', 'ä', 'Á', 'č', 'Č', 'ď', 'Ď', 'é', 'ě', 'ë',
			'É', 'Ě', 'í', 'Í', 'ľ', 'ĺ', 'Ľ', 'Ĺ', 'ň', 'Ň', 'ó', 'ö', 'ô',
			'Ó', 'ř', 'Ř', 'š', 'Š', 'ť', 'Ť', 'ú', 'ů', 'ü', 'Ú', 'ý', 'Ý',
			'ž', 'Ž'
		], [
			'-', ' ', '-', 'a', 'a', 'a', 'c', 'c', 'd', 'd', 'e', 'e', 'e',
			'e', 'e', 'i', 'i', 'l', 'l', 'l', 'l', 'n', 'n', 'o', 'o', 'o',
			'o', 'r', 'r', 's', 's', 't', 't', 'u', 'u', 'u', 'u', 'y', 'y',
			'z', 'z'
		], $string);

		$string = strtolower($string);

		for ($i = 0; $i < strlen($string); $i++)
		{
			$tmp = ord($string[ $i ]);

			if (($tmp < 58 AND $tmp > 47) OR ($tmp > 96 AND $tmp < 123) OR $tmp == 45)
			{
				$return .= $string[ $i ];
			}
		}

		return $return;
	}

	protected function set_authorization(Array $auth)
	{
		$this->auth = $auth;
	}

	protected function validate_required_fields()
	{
		foreach ($this->required_fields as $required_field)
		{
			if (!isset($this->fields[ $required_field ]) OR empty($this->fields[ $required_field ]))
				throw new InvalidRequestException(sprintf(
					"Required field '%s' is missing a value.", $required_field
				));
		}
	}

	protected function format_mobile_number($mobile_number)
	{
		$mobile_number = preg_replace('/( |-|\(|\))/i', '', $mobile_number);

		if (preg_match('/^0[0-9]{9}$/', $mobile_number))
		{
			$mobile_number = preg_replace('/^0/', '+421', $mobile_number);
		}
		else if (preg_match('/^[0-9]{9}$/', $mobile_number))
		{
			$mobile_number = '+421' . $mobile_number;
		}
		else if (preg_match('/^00/', $mobile_number))
		{
			$mobile_number = preg_replace('/^00/', '+', $mobile_number);
		}

		return (strlen($mobile_number) === 13 AND preg_match('/^\+/', $mobile_number))
			? $mobile_number
			: NULL;
	}

	protected function detect_client_language(Array $available_languages)
	{
		$http_accept_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
			? $_SERVER['HTTP_ACCEPT_LANGUAGE']
			: '';

		// Standard for HTTP_ACCEPT_LANGUAGE is defined under
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
		preg_match_all("/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?" .
			"(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
			$http_accept_language, $hits, PREG_SET_ORDER
		);

		// Default language (in case of no hits) is the first in the array
		$bestlang = $available_languages[ 0 ];
		$bestqval = 0;

		foreach ($hits as $arr)
		{
			$langprefix = strtolower($arr[ 1 ]);

			if (!empty($arr[ 3 ]))
			{
				$langrange = strtolower($arr[ 3 ]);
				$language = $langprefix . '-' . $langrange;
			}
			else
			{
				$language = $langprefix;
			}

			$qvalue = !empty($arr[ 5 ]) ? floatval($arr[ 5 ]) : 1.0;

			// Find q-maximal language
			if (in_array($language, $available_languages) AND ($qvalue > $bestqval)) {
				$bestlang = $language;
				$bestqval = $qvalue;
			}

			// If no direct hit, try the prefix only but decrease q-value
			// by 10% (as http_negotiate_language does)
			else if (in_array($langprefix, $available_languages) AND (($qvalue * 0.9) > $bestqval))
			{
				$bestlang = $langprefix;
				$bestqval = $qvalue * 0.9;
			}
		}

		return $bestlang;
	}

	/**
	 * Detect (based on user agent string from the client) whether a mobile
	 * device is being used (tablets are not considered to be mobile here).
	 *
	 * @return 	bool
	 * @author 	Chad Smith <http://detectmobilebrowsers.com>
	 */
	protected function detect_mobile_request()
	{
		if (empty($this->client_user_agent))
			return FALSE;

		return (bool) (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $this->client_user_agent) OR preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($this->client_user_agent, 0, 4)));
	}
}

trait ECDSAResponseValidator
{
	public $ecdsa_keys 		= [];

	public function validate_ecdsa_signature()
	{
		if (count($this->ecdsa_keys) === 0)
		{
			trigger_error(
				"There are no valid Tatra banka's ECDSA keys. Check " .
				"whether you set `ecdsa_keys_file` to a correct file " .
				"in options array when creating chaching instance to " .
				"take advantage of additional security when validating " .
				"messages from the bank.",
				E_USER_WARNING
			);
		}
		else if (!isset($this->fields['ECDSA_KEY']) OR !is_numeric($this->fields['ECDSA_KEY']) OR !isset($this->ecdsa_keys[ $this->fields['ECDSA_KEY'] ]))
		{
			trigger_error(
				sprintf(
					"There is no valid Tatra banka's ECDSA key with " .
					"`KEY_ID` of '%s'. Update your `ecdsa_keys_file` " .
					"set in options array when creating chaching " .
					"instance to take advantage of additional security " .
					"when validating messages from the bank.",
					$this->fields['ECDSA_KEY']
				),
				E_USER_WARNING
			);
		}
		else if (!extension_loaded('openssl'))
		{
			trigger_error(
				"OpenSSL extension of PHP is not available and thus " .
				"`openssl_verify` function is not available to check " .
				"ECDSA signature sent by the bank. Install and load " .
				"OpenSSL extension to take advantage of additional " .
				"security when validating messages from the bank.",
				E_USER_WARNING
			);
		}
		else
		{
			$signature_base = $this->ecdsa_signature_base();

			$openssl_result = openssl_verify(
				$signature_base,
				pack('H*', $this->fields['ECDSA']),
				$this->ecdsa_keys[ $this->fields['ECDSA_KEY'] ],
				OPENSSL_ALGO_SHA256
			);

			switch ($openssl_result)
			{
				case 1:
					return [ TRUE, NULL ];

				case -1:
					return [
						FALSE,
						sprintf(
							"There was an OpenSSL error when validating " .
							"ECDSA signature using `openssl_verify`: '%s'.",
							openssl_error_string()
						)
					];

				case 0:
				default:
					return [
						FALSE,
						sprintf(
							"ECDSA Signature received as part of the " .
							"response is incorrect (got '%s'). If this " .
							"persists contact the bank.",
							$this->fields['ECDSA']
						)
					];
			}
		}

		return [ TRUE, NULL ];
	}
}

interface ECDSAResponseInterface
{
	public function ecdsa_signature_base();
	public function validate_ecdsa_signature();
}
