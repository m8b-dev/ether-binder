<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Utils\OOGmp;

class AbiInt extends AbiUint
{
	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct(null|int|OOGmp $val, int $maxBits)
	{
		if(!($val instanceof OOGmp))
			$val = new OOGmp($val);
		if(strlen(gmp_strval($val->raw(),2)) > ($maxBits) - 1 /* first bit for denominating sign*/) {
			throw new EthBinderArgumentException("value is too big for size of the variable");
		}
		$this->value = $val;
	}
}
