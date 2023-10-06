<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Utils\Functions;

/**
 * Hash is a class for representing Ethereum hashes and sets structure and method for another single value data types
 * such as Ethereum Address.
 *
 * @author DubbaThony
 */
class Hash implements BinarySerializableInterface, HashSerializable
{
	protected const dataSizeBytes = 32;
	protected string $bytes;

	private function __construct(string $bytes)
	{
		$this->bytes = $bytes;
	}

	/**
	 * Initializes from a hexadecimal string.
	 *
	 * @param string $hex Hexadecimal data.
	 * @return static
	 * @throws InvalidHexLengthException
	 * @throws InvalidHexException
	 */
	public static function fromHex(string $hex): static
	{
		Functions::mustHexLen($hex, static::dataSizeBytes * 2);
		return new static(Functions::hex2bin($hex));
	}

	/**
	 * Initializes from a binary string.
	 *
	 * @param string $bin Binary data.
	 * @return static
	 * @throws InvalidLengthException
	 */
	public static function fromBin(string $bin): static
	{
		if(strlen($bin) != static::dataSizeBytes) {
			throw new InvalidLengthException(static::dataSizeBytes, strlen($bin));
		}
		return new static($bin);
	}

	/**
	 * Converts the internal data into to a hexadecimal string.
	 *
	 * @param bool $with0x Flag to include "0x" prefix or not.
	 * @return string Hexadecimal representation.
	 */
	public function toHex(bool $with0x = true): string
	{
		return ($with0x ? "0x" : "").bin2hex($this->bytes);
	}

	/**
	 * Converts the internal data into to a binary string.
	 *
	 * @return string Binary representation.
	 */
	public function toBin(): string
	{
		return $this->bytes;
	}

	/**
	 * Checks for equality with another Hash or Hash-derivative object.
	 *
	 * @param static $b Another object to compare against.
	 * @return bool True if equal, false otherwise.
	 */
	public function eq(Hash $b): bool
	{
		return $this->bytes == $b->bytes;
	}

	/**
	 * Initializes object with all zeros.
	 *
	 * @return static The null object.
	 */
	public static function NULL(): static
	{
		return new static(str_repeat("\0", static::dataSizeBytes));
	}

	/**
	 * Checks if inner data contains only zeroes (equals to `static::NULL()`)
	 *
	 * @return bool true if inner data is null data, false otherwise
	 */
	public function isNull(): bool
	{
		return $this->eq(static::NULL());
	}
}
