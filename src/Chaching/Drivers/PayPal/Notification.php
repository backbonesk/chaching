<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2019 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\PayPal;

use \Chaching\Chaching;
use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Encryption\Hmac;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\TransactionStatuses;


class Notification extends \Chaching\Message
{
	const PREFERRED_ENCODING 		= 'utf-8';

	public $status 					= FALSE;
	public $reference_number 		= NULL;
	public $transaction_id 			= NULL;

	private $notification_options 	= [];

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->notification_options 	= $attributes;
		$this->readonly_fields 			= [
			'business', 'receiver_id', 'receiver_email',
			'custom', 'txn_id', 'txn_type',
			'item_name', 'item_number', 'quantity',
			'payment_status', 'payment_type',
			'payer_id', 'payer_email', 'payer_status',
			'first_name', 'last_name', 'residence_country',
			'ipn_track_id', 'notify_version',
			'mc_currency', 'mc_fee', 'mc_gross'
		];

		$original_encoding = isset($attributes['charset'])
			? strtolower($attributes['charset'])
			: self::PREFERRED_ENCODING;

		$is_encoding_conversion_required = (
			$original_encoding !== self::PREFERRED_ENCODING
		);

		foreach ($this->readonly_fields as $field_name)
		{
			if (empty($attributes[ $field_name ]))
			{
				$this->fields[ $field_name ] = NULL;

				continue;
			}

			if ($is_encoding_conversion_required AND $this->is_mbstring_supported())
			{
				$this->fields[ $field_name ] = mb_convert_encoding(
					$attributes[ $field_name ],
					self::PREFERRED_ENCODING,
					$original_encoding
				);
			}
			else
			{
				$this->fields[ $field_name ] = $attributes[ $field_name ];
			}
		}

		$this->set_authorization($authorization);

		if (!empty($options))
		{
			$this->set_options($options);
		}

		$this->validate();
	}

	private function is_mbstring_supported()
	{
		static $is_supported = NULL;

		if ($is_supported === NULL)
		{
			$is_supported = extension_loaded('mbstring');
		}

		return $is_supported;
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		if ($this->validate_notification() !== TRUE)
			return ($this->status = NULL);

		switch ($this->fields['payment_status'])
		{
			case 'Completed':
				$this->status = TransactionStatuses::SUCCESS;
				break;

			case 'Denied':
			case 'Failed':
				$this->status = TransactionStatuses::FAILURE;
				break;

			case 'Pending':
			case 'In-Progress':
				$this->status = TransactionStatuses::PENDING;
				break;

			case 'Expired':
				$this->status = TransactionStatuses::EXPIRED;
				break;

			default:
				$this->status = TransactionStatuses::UNKNOWN;
				break;
		}

		$this->reference_number = $this->fields['custom'];

		if (isset($this->fields['txn_id']))
		{
			$this->transaction_id = $this->fields['txn_id'];
		}
	}

	private function validate_notification()
	{
		// $context = stream_context_create([
		// 	'http' => [
		// 		'header' 	=>
		// 			"Content-type: application/x-www-form-urlencoded\r\n",
		// 		'method' 	=> 'POST',
		// 		'content' 	=> http_build_query($this->notification_options)
		// 	]
		// ]);

		// return (bool) (strstr(file_get_contents(
		// 	$this->request_server_url(),
		// 	FALSE,
		// 	$context
		// ), 'VERIFIED') === 'VERIFIED');

		$raw_post_data = file_get_contents('php://input');
		$raw_post_array = explode('&', $raw_post_data);
		$myPost = array();
		foreach ($raw_post_array as $keyval) {
		  $keyval = explode ('=', $keyval);
		  if (count($keyval) == 2)
		    $myPost[$keyval[0]] = urldecode($keyval[1]);
		}
		// read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
		$req = 'cmd=_notify-validate';
		if (function_exists('get_magic_quotes_gpc')) {
		  $get_magic_quotes_exists = true;
		}
		foreach ($myPost as $key => $value) {
		  if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
		    $value = urlencode(stripslashes($value));
		  } else {
		    $value = urlencode($value);
		  }
		  $req .= "&$key=$value";
		}

		// Step 2: POST IPN data back to PayPal to validate
		$ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
		// curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
		if ( !($res = curl_exec($ch)) ) {
		  // error_log("Got " . curl_error($ch) . " when processing IPN data");
		  curl_close($ch);
		  exit;
		}
		curl_close($ch);

		if (strcmp ($res, "VERIFIED") == 0) {
		  // The IPN is verified, process it
			return true;
		} 
		// IPN invalid, log for manual investigation
		return false;
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate'
			: 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate';
	}
}
