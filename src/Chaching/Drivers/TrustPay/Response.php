<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2014 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TrustPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;

final class Response extends \Chaching\Messages\Hmac
{
	public $status 				= FALSE;
	public $reference_number 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'REF', 'RES', 'PID'
		);

		$this->fields = array(
			'REF' 	=> isset($options['REF']) ? $options['REF'] : NULL,
			'RES' 	=> isset($options['RES']) ? $options['RES'] : NULL,
			'PID' 	=> isset($options['PID']) ? $options['PID'] : NULL
		);

		$this->set_authorization($authorization);

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		$this->reference_number 	= $this->fields['REF'];

		if ($this->fields['RES'] == 0)
		{
			$this->status = \Chaching\Statuses::SUCCESS;
		}
		else
		{
			$this->status = \Chaching\Statuses::FAILURE;
		}

		return $this->status;
	}
}
