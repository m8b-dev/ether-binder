<?php

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Common\Address;

class AbiAddress extends AbstractABIValue
{
	public Address $data;

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return str_repeat(chr(0), 32 - 20) . $this->data->toBin();
	}

}