<?php
namespace Chaching\Drivers\TBTatraPay;

use \Chaching\Driver;
use \Chaching\Currencies;
use \Chaching\Exceptions\InvalidOptionsException;

final class Response extends \Chaching\Messages\Des
{
	public $status 				= FALSE;
	public $variable_symbol 	= NULL;
	public $specific_symbol 	= NULL;

	public function __construct(Array $authorization, Array $options)
	{
		parent::__construct();

		$this->readonly_fields = array(
			'SS', 'VS', 'RES', 'SIGN'
		);

		$this->fields = array(
			'SS' 	=> isset($options['SS']) ? $options['SS'] : NULL,
			'VS' 	=> isset($options['VS']) ? $options['VS'] : NULL,
			'RES' 	=> isset($options['RES']) ? $options['RES'] : NULL,
			'SIGN' 	=> isset($options['SIGN']) ? $options['SIGN'] : NULL
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

		$this->fields['RES'] = strtolower($this->fields['RES']);

		$this->variable_symbol 	= $this->fields['VS'];
		$this->specific_symbol 	= $this->fields['SS'];

		if ($this->fields['RES'] === 'ok' AND !empty($this->fields['VS']))
		{
			$this->status = \Chaching\Statuses::SUCCESS;
		}
		else if ($this->fields['RES'] === 'tout')
		{
			$this->status = \Chaching\Statuses::TIMEOUT;
		}
		else
		{
			$this->status = \Chaching\Statuses::FAILURE;
		}

		return $this->status;
	}

	protected function signature_base()
	{
		return $this->fields['VS'] . $this->fields['SS'] . $this->fields['RES'];
	}
}
