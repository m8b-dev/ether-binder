<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Exceptions;

class BadAddressChecksumException extends \InvalidArgumentException
{
	public function __construct(string $badSum, string $goodSum)
	{
		parent::__construct("bad checksum in address: expected correct checksum ($goodSum) but got invalid checksum ($badSum)", 0);
	}
}
