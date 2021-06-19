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
use \Chaching\Exceptions\InvalidOptionsException;
use \Chaching\TransactionStatuses;


class Response extends \Chaching\Message
{
	public $transaction_id 		= NULL;
	public $transaction_result 	= NULL;
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public $verbose_response 	= '';

	public function __construct(Array $authorization, Array $attributes, Array $options = [])
	{
		parent::__construct();

		$this->readonly_fields = [ 'p', 'r', 'o', 'g', 'a' ];

		$this->set_authorization($authorization);

		if (!empty($attributes['o']))
		{
			$this->variable_symbol = $attributes['o'];
		}

		if (!empty($attributes['g']))
		{
			$this->transaction_id = $attributes['g'];
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

			$response = $client->VerifyTransactionByGuid([
				'eshop_id' 			=> $this->auth[ 0 ],
				'order_num' 		=> $this->variable_symbol,
				'external_guid' 	=> $this->transaction_id
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

		if (!isset($response->VerifyTransactionByGuidResult))
			throw new InvalidResponseException(sprintf(
				"There was an error with invalid response from the bank" .
				"the bank: `VerifyTransactionByGuidResult` object cannot " .
				"be found (%s)",
				$this->verbose_response
			));

		$status_code = (int) $response->VerifyTransactionByGuidResult->ResultCode;

		if ((bool) $response->VerifyTransactionByGuidResult->IsAuthorized === TRUE AND $status_code === 4)
		{
			$this->status =  TransactionStatuses::SUCCESS;
		}
		else
		{
			switch ($status_code)
			{
				case 1:
				case 2:
				case 3:
					$this->status =  TransactionStatuses::PENDING;
					break;

				case 7:
					$this->status =  TransactionStatuses::REVERSED;
					break;

				case 5:
				case 6:
				default:
					$this->status =  TransactionStatuses::FAILURE;
					break;
			}
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
