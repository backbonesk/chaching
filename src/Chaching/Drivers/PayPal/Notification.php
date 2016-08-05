<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
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
	public $status 					= FALSE;
	public $reference_number 		= NULL;
	public $transaction_id 			= NULL;

	private $notification_options 	= [];

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->notification_options 	= $attributes;
		$this->readonly_fields 			= [
			'business', 'custom', 'payment_status', 'txn_id'
		];

		foreach ($this->readonly_fields as $field_name)
		{
			$this->fields[ $field_name ] = isset($attributes[ $field_name ])
				? $attributes[ $field_name ]
				: NULL;
		}

		$this->set_authorization($authorization);

		if (!empty($options))
		{
			$this->set_options($options);
		}

		$this->validate();
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

		$this->reference_nuber = $this->fields['custom'];

		if (isset($this->fields['txn_id']))
		{
			$this->transaction_id = $this->fields['txn_id'];
		}
	}

	private function validate_notification()
	{
		$context = stream_context_create([
			'http' => [
				'header' 	=>
					"Content-type: application/x-www-form-urlencoded\r\n",
				'method' 	=> 'POST',
				'content' 	=> http_build_query($this->notification_options)
			]
		]);

		return (bool) (strstr(file_get_contents(
			$this->request_server_url(),
			FALSE,
			$context
		), 'VERIFIED') === 'VERIFIED');
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate'
			: 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate';
	}
}
