<?php

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Utils\OOGmp;

class AbiUint extends AbstractABIValue
{
	public OOGmp $value;

	public function __construct(int|OOGmp $val, int $maxBits)
	{
		if(!($val instanceof OOGmp))
			$val = new OOGmp($val);
		if(strlen(gmp_strval($val->raw(),2)) > $maxBits) {
			throw new EthBinderArgumentException("value is too big for size of the variable");
		}
		$this->value = $val;
	}

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return $this->value->toBin(32);
	}

	public function __toString(): string
	{
		return $this->value->toString();
	}
}
