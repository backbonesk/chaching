<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2021 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;

/*
 * Payment and transaction statuses.
 */
final class TransactionStatuses
{
	const SUCCESS 	= 'success';
	const FAILURE 	= 'failure';
	const PENDING 	= 'pending';
	const EXPIRED 	= 'expired';
	const TIMEOUT 	= 'timeout';
	const UNKNOWN 	= 'unknown';
	const REVERSED 	= 'reversed';
}
