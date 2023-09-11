<?php

namespace M8B\EtherBinder\Contract;

abstract class AbstractEvent
{
	abstract static function getEventData(): array;
	public function getDataByName(string $name)
	{
		// todo: implement me
		return "";
	}
}