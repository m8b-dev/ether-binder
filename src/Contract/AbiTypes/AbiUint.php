<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Utils\OOGmp;

class AbiUint extends AbstractABIValue
{
	public OOGmp $value;

	public function __construct(null|int|OOGmp $val, int $maxBits)
	{
		if(!($val instanceof OOGmp))
			$val = new OOGmp($val);
		if($val->lt(0))
			throw new EthBinderArgumentException("value is lower than 0, cannot be unsigned int");
		if(strlen(gmp_strval($val->raw(),2)) > $maxBits) {
			throw new EthBinderArgumentException("value is too big for size of the variable");
		}
		$this->value = $val;
	}

	public function decodeBin(string &$dataBin, int $globalOffset): int
	{
		$this->value = new OOGmp(bin2hex(substr($dataBin, $globalOffset, 32)), 16);
		return 32;
	}

	public function __toString(): string
	{
		return $this->value->toString();
	}

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return $this->value->toBin(32);
	}
	public function unwrapToPhpFriendlyVals(?array $tuplerData): int|OOGmp
	{
		return $this->value;
	}
}
