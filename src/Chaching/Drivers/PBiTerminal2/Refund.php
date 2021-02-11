<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Drivers\PBiTerminal2;

use \Chaching\Chaching;
use \Chaching\Drivers\PBiTerminal\Refund as PBiTerminalRefund;


class Refund extends PBiTerminalRefund
{
	protected function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://vpos.te.sia.eu:8443/ecomm/MerchantHandler'
			: 'https://vpos.sia.eu:8443/ecomm/MerchantHandler';
	}
}
