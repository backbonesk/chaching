<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\PBiTerminal;

use \Chaching\Driver;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\TransactionStatuses;
use \Chaching\Transport\Curl;


class Response extends \Chaching\Message
{
	const REQUEST_SERVER_URI 	=
		'https://secureshop.firstdata.lv:8443/ecomm/MerchantHandler';

	public $status 				= FALSE;
	public $card_no 			= NULL;
	public $transaction_id 		= NULL;

	public $verbose_response 	= '';

	public function __construct(Array $authorization, Array $attributes)
	{
		parent::__construct();

		$this->readonly_fields = [
			'trans_id', 'command', 'RESULT', 'RESULT_CODE', 'CARD_NUMBER',
			'3DSECURE', 'APPROVAL_CODE', 'RRN'
		];

		$this->set_authorization($authorization);

		$this->fields['command'] 			= 'c';
		$this->fields['client_ip_addr'] 	= isset($_SERVER['REMOTE_ADDR'])
			? $_SERVER['REMOTE_ADDR']
			: $_SERVER['SERVER_ADDR'];

		if (!empty($attributes['trans_id']))
		{
			$this->fields['trans_id'] = $attributes['trans_id'];
		}

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		if (!is_array($this->auth) OR count($this->auth) !== 2)
			throw new InvalidAuthorizationException(
				"Merchant information incorrect. In case of PB iTerminal, " .
				"you need to provide an array with "
			);

		if (!is_array($this->auth[ 1 ]))
			throw new InvalidAuthorizationException(
				"Incorrect settings for shared secret. In case of " .
				"iTerminal, you need to provide an array with " .
				"`keystore` key with paths to '.pem' certificate and " .
				"`password` key with corresponding password."
			);

		if (!isset($this->auth[ 1 ]['keystore']) OR empty($this->auth[ 1 ]['keystore']))
			throw new InvalidAuthorizationException(
				"Authorization information (`keystore` key) is missing " .
				"a value. Try changing it to a file path with the '.pem' " .
				"file you generated according to the documentation."
			);

		if (!is_file($this->auth[ 1 ]['keystore']) OR !is_readable($this->auth[ 1 ]['keystore']))
		{
			throw new InvalidAuthorizationException(sprintf(
				"Authorization information (`keystore` key) has " .
				"an unacceptable value pointing to a missing or unreadable ".
				"file '%s'. Upload the file and set the file path " .
				"to it's new location.", $this->auth[ 1 ]['keystore']
			));
		}

		if (!isset($this->auth[ 1 ]['password']) OR empty($this->auth[ 1 ]['password']))
			throw new InvalidAuthorizationException(
				"The password to certificate provided in `password` is " .
				"missing value."
			);

		if (!isset($this->fields['trans_id']) OR empty($this->fields['trans_id']))
			throw new InvalidResponseException(
				"Bank transaction identifier is missing."
			);

		$request = new Curl(
			Curl::METHOD_POST, self::REQUEST_SERVER_URI,
			http_build_query($this->fields),
			[
				CURLOPT_SSLKEYPASSWD 	=> $this->auth[ 1 ]['password'],
				CURLOPT_SSLCERT 		=> $this->auth[ 1 ]['keystore'],
				CURLOPT_CAINFO 			=> $this->auth[ 1 ]['keystore'],
				CURLOPT_SSL_VERIFYPEER 	=> TRUE,
				CURLOPT_VERBOSE 		=> TRUE
			]
		);

		$this->transaction_id 		= $this->fields['trans_id'];
		$this->verbose_response 	= $request->content();

		if (preg_match_all('/([A-Z0-9\_]+): (.+)\s*/', $this->verbose_response, $matches))
		{
			foreach ($matches[ 1 ] as $key => $header)
			{
				$header = strtoupper($header);

				switch ($header)
				{
					case 'RESULT':
						$this->status = ($matches[ 2 ][ $key ] === 'OK')
							? TransactionStatuses::SUCCESS
							: TransactionStatuses::FAILURE;
						break;

					case 'CARD_NUMBER':
						$this->card_no = $matches[ 2 ][ $key ];
						break;

					default:
						$this->fields[ $header ] = $matches[ 2 ][ $key ];
						break;
				}
			}
		}

		return $this->status;
	}
}
