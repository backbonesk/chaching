<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2020 BACKBONE, s.r.o.
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

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'RES', 'REF', 'PID' ];

		foreach ($this->readonly_fields as $field)
		{
			$this->fields[ $field ] = !empty($attributes[ $field ])
				? $attributes[ $field ]
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
		$this->reference_number = $this->fields['REF'];

		$this->status = ($this->fields['RES'] == 0)
			? TransactionStatuses::SUCCESS
			: TransactionStatuses::FAILURE;

		return $this->status;
	}
}
