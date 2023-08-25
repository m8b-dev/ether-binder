<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Exceptions;

class InvalidLengthException extends \InvalidArgumentException
{
	public function __construct(int $wantLen, int $gotLen)
	{
		parent::__construct("got invalid parameter length, expected length $wantLen, but got $gotLen", 0, null);
	}
}
