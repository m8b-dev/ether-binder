<?php

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Utils\OOGmp;

class AbiArrayKnownLength extends AbiTuple
{
	public function __construct(/*public int $length*/) {

	}


	public function __toString(): string
	{
		$ret = "k[";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").(string)$inr;
		}
		$ret .= "]";
		return $ret;
	}
	/*
	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		foreach($this->inner AS $itm) {
			$data .= $itm->encodeBin();
		}
		return $data;
	}*/
}
