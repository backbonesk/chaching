<?php
namespace Chaching\Drivers\TBCardPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;

final class Response extends \Chaching\Messages\Des
{
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'VS', 'RES', 'SIGN', 'AC'
		);

		$this->fields = array(
			'VS' 	=> isset($options['VS']) ? $options['VS'] : NULL,
			'RES' 	=> isset($options['RES']) ? $options['RES'] : NULL,
			'SIGN' 	=> isset($options['SIGN']) ? $options['SIGN'] : NULL,
			'AC' 	=> isset($options['AC']) ? $options['AC'] : NULL
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
		$signature = $this->sign($this->signature_base());

		if ($this->fields['SIGN'] !== $signature)
			throw new \Chaching\Exceptions\InvalidResponseException(sprintf(
				"Signature received as part of the response is incorrect (" .
				"'%s' expected, got '%s'). If this persists contact the bank.",
				$signature, $this->fields['SIGN']
			));

		$this->variable_symbol 	= $this->fields['VS'];
		$this->status 			= ($this->fields['RES'] === 'OK' AND !empty($this->fields['VS']))
			? \Chaching\Statuses::SUCCESS
			: \Chaching\Statuses::FAILURE;

		return $this->status;
	}

	protected function signature_base()
	{
		return $this->fields['VS'] . $this->fields['RES'] . $this->fields['AC'];
	}
}
