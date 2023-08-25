<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Utils\Functions;

class Hash
{
	protected const dataSizeBytes = 32;
	protected string $bytes;

	private function __construct(string $bytes)
	{
		$this->bytes = $bytes;
	}

	public static function fromHex(string $hex): static
	{
		Functions::mustHexLen($hex, static::dataSizeBytes * 2);
		if(str_starts_with($hex, "0x")) {
			$hex = substr($hex, 2);
		}
		return new static(hex2bin($hex));
	}

	public static function fromBin(string $bin): static
	{
		if(strlen($bin) != static::dataSizeBytes) {
			throw new InvalidLengthException(static::dataSizeBytes, strlen($bin));
		}
		return new static($bin);
	}

	public function toHex(bool $with0x = true): string
	{
		return ($with0x ? "0x" : "").bin2hex($this->bytes);
	}

	public function toBin(): string
	{
		return $this->bytes;
	}

	public function eq(Hash $b): bool
	{
		return $this->bytes == $b->bytes;
	}

	public static function NULL(): static
	{
		return new static(str_repeat("\0", static::dataSizeBytes));
	}
}
