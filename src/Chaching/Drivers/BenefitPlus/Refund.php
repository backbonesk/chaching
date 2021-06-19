<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\BenefitPlus;

use SOAPClient;

use \Chaching\Chaching;
use \Chaching\Driver;
use \Chaching\Exceptions\InvalidAuthorizationException;
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\Exceptions\InvalidResponseException;
use \Chaching\TransactionStatuses;


class Refund extends \Chaching\Message
{
	public $status 				= FALSE;
	public $verbose_response 	= '';

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'eshop_id' ];
		$this->required_fields = [ 'order_num', 'price' ];
		$this->optional_fields = [ 'note' ];

		$this->set_authorization($authorization);

		$this->field_map = [
			Driver::AMOUNT 				=> 'price',
			Driver::VARIABLE_SYMBOL 	=> 'order_num'
		];

		if (!empty($attributes))
		{
			$this->set_attributes($attributes);
		}

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
		if (!is_array($this->auth) OR empty($this->auth[ 0 ]))
			throw new InvalidAuthorizationException("Eshop ID is missing.");

		if (!preg_match('/^[a-zA-Z0-9]{8}\-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/', $this->auth[ 0 ]))
			throw new InvalidOptionsException(sprintf(
				"Authorization information (Eshop ID) has an " .
				"unacceptable value '%s'. Try changing it to value you " .
				"got from Benefit Plus.", $this->auth[ 0 ]
			));

		try
		{
			$client = new SOAPClient(
				sprintf('%s?WSDL', $this->request_server_url()),
				[
					'trace' => 1,
					'soap_version' => SOAP_1_2,
					'user_agent' => 'chaching-php-' . Chaching::VERSION,
				]
			);

			$response = $client->CancelTransaction([
				'eshop_id' 		=> $this->auth[ 0 ],
				'order_num' 	=> $this->variable_symbol,
				'price' 		=> $this->fields['price'],
				'note' 			=> $this->fields['note']
			]);
		}
		catch (Exception $e)
		{
			throw new InvalidResponseException(sprintf(
				"Web service used to validate payment has failed: %s",
				$e->getMessage()
			));
		}

		$this->verbose_response = $client->__getLastResponse();

		if (!isset($response->CancelTransactionResult))
			throw new InvalidResponseException(sprintf(
				"There was an error with invalid response from the bank" .
				"the bank: `CancelTransactionResult` object cannot " .
				"be found (%s)",
				$this->verbose_response
			));

		$status_code = (int) $response->CancelTransactionResult->ResultCode;

		if ((bool) $response->CancelTransactionResult->IsAuthorized === TRUE AND $status_code === 7)
		{
			$this->status =  TransactionStatuses::REVERSED;
		}
		else
		{
			$this->status =  TransactionStatuses::FAILURE;

			$error_message = (
				"There was an error with processing at the bank " .
				"and the refund transaction has not been executed."
			);

			if (!empty($response->CancelTransactionResult->ErrorText))
			{
				$error_message .= sprintf(
					" Error: %s",
					$response->CancelTransactionResult->ErrorText
				);
			}

			throw new InvalidResponseException($error_message);
		}

		return $this->status;
	}

	private function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://benefitv3.sprinx.cz/ws/PayGate.asmx'
			: 'https://inside.benefit-plus.eu/ws/PayGate.asmx';
	}
}
