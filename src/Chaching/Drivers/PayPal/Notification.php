<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2015 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\PayPal;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Encryption\Hmac;
use \Chaching\TransactionStatuses;
use \Chaching\Exceptions\InvalidOptionsException;

class Notification extends \Chaching\Message
{
	const REQUEST_URI = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate';

	public $status 					= FALSE;
	public $reference_number 		= NULL;

	private $notification_options 	= [];

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->notification_options = $options;
		$this->readonly_fields = array('business', 'custom', 'payment_status');

		foreach ($this->readonly_fields as $field_name)
		{
			$this->fields[ $field_name ] = isset($options[ $field_name ])
				? $options[ $field_name ]
				: NULL;
		}

		$this->set_authorization($authorization);

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
			self::REQUEST_URI, FALSE, $context
		), 'VERIFIED') === 'VERIFIED');
	}
}
