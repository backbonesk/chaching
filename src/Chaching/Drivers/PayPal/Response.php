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

use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $status 	= FALSE;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->set_authorization($authorization);

		$this->validate();
	}

	/**
	 * @return 	bool
	 * @throw 	\Chaching\Exceptions\InvalidResponseException
	 */
	protected function validate()
	{
		return ($this->status = TransactionStatuses::PENDING);
	}
}
