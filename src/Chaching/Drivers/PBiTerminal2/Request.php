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
use \Chaching\Drivers\PBiTerminal\Request as PBiTerminalRequest;


class Request extends PBiTerminalRequest
{
	protected function request_server_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://vpos.te.sia.eu:8443/ecomm/MerchantHandler'
			: 'https://vpos.sia.eu:8443/ecomm/MerchantHandler';
	}

	protected function request_client_url()
	{
		return ($this->environment === Chaching::SANDBOX)
			? 'https://vpos.te.sia.eu/ecomm/ClientHandler'
			: 'https://vpos.sia.eu/ecomm/ClientHandler';
	}
}
