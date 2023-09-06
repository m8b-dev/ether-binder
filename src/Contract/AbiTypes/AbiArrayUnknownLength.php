<?php

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Utils\OOGmp;

class AbiArrayUnknownLength extends AbiArrayKnownLength
{
	public function isDynamic(): bool
	{
		return true;
	}

	public function encodeBin(): string
	{
		$data = (new OOGmp(count($this->inner)))->toBin(32);
		return $data . parent::encodeBin();
	}

	public function __toString(): string
	{
		$ret = "u[";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").(string)$inr;
		}
		$ret .= "]";
		return $ret;
	}
}