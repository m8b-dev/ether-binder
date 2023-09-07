<?php

namespace M8B\EtherBinder\Contract;

abstract class AbstractTuple implements \ArrayAccess
{
	private array $store = [];

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->store[$offset]);
	}

	public function offsetGet(mixed $offset): mixed
	{
		return $this->store[$offset] ?? null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (is_null($offset)) {
			$this->store[] = $value;
		} else {
			$this->store[$offset] = $value;
		}
	}

	public function offsetUnset(mixed $offset): void
	{
		unset($this->store[$offset]);
	}
}