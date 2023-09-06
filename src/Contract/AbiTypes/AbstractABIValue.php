<?php

namespace M8B\EtherBinder\Contract\AbiTypes;

abstract class AbstractABIValue
{
	public static function parseValue(string $type, mixed $value): AbstractABIValue
	{

		//switch($type)
		//var_dump($type);
		//var_dump($value);
		return new AbiUint($value, 256);
	}

	abstract public function isDynamic(): bool;
	abstract public function encodeBin(): string;
}
