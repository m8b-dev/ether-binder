<?php

namespace M8B\EtherBinder\Contract\AbiTypes;

class AbiBool extends AbstractABIValue
{
	public bool $val;

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return str_repeat(chr(0), 31) . ($this->val ? chr(1) : chr(0));
	}

}