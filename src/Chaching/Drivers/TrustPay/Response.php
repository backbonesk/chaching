<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\TrustPay;

use \Chaching\Currencies;
use \Chaching\Driver;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $status 				= FALSE;
	public $reference_number 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = [ 'RES', 'REF', 'PID' ];

		$this->fields = [
			'RES' 	=> isset($options['RES']) ? $options['RES'] : NULL,
			'REF' 	=> (isset($options['REF']) AND !empty($options['REF']))
				? $options['REF']
				: NULL,
			'PID' 	=> (isset($options['PID']) AND !empty($options['PID']))
				? $options['PID']
				: NULL
		];

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
			$this->status = TransactionStatuses::SUCCESS;
		}
		else
		{
			$this->status = TransactionStatuses::FAILURE;
		}

		return $this->status;
	}
}
