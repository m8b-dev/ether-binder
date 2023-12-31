<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * @author DubbaThony (structure, abstraction, bugs)
 * @author gh/VOID404 (maths)
 */
class AbiInt extends AbiUint
{
	/**
	 * @throws EthBinderArgumentException
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct(null|int|OOGmp $val, protected int $maxBits)
	{
		if(!($val instanceof OOGmp))
			$val = new OOGmp($val);
		if(strlen(gmp_strval($val->raw(),2)) > ($maxBits) - 1 /* first bit for denominating sign*/) {
			throw new EthBinderArgumentException("value is too big for size of the variable");
		}
		$this->value = $val;
	}

	/**
	 * @inheritDoc
	 */
	public function __toString(): string
	{
		return $this->value?->toString() ?? "int".$this->maxBits;
	}
}
