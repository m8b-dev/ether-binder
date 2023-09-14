<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use ArrayAccess;

/**
 * Abstract class defining simple ArrayAccess implementation for general purpose binding or ABI-related array, tuples or
 * events classes.
 *
 * @author DubbaThony
 */
class AbstractArrayAccess implements ArrayAccess
{
	private array $store = [];

	/**
	 * @inheritDoc
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->store[$offset]);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->store[$offset] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (is_null($offset)) {
			$this->store[] = $value;
		} else {
			$this->store[$offset] = $value;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset(mixed $offset): void
	{
		unset($this->store[$offset]);
	}
}