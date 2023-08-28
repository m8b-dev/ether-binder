<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Utils;

use GMP;
use RuntimeException;

class OOGmp
{
	private GMP $gmp;
	public function __construct(null|string|GMP $number = null, ?int $base = null)
	{
		if($number === null) {
			$this->gmp = gmp_init(0);
			return;
		}
		if($number instanceof GMP) {
			$this->gmp = $number;
			return;
		}

		if($base !== null) {
			$this->gmp = gmp_init($number, $base);
		} else {
			if(ctype_digit($number))
				$this->gmp = gmp_init($number, 10);
			else
				$this->gmp = gmp_init($number, 16);
		}
	}

	public static function wrap(GMP $raw): static
	{
		$static = new static();
		$static->gmp = $raw;
		return $static;
	}

	public function toInt(): int
	{
		if(gmp_cmp($this->gmp, PHP_INT_MAX) > 0) {
			throw new RuntimeException("number is bigger than PHP_INT_MAX and toInt is not available");
		}
		return gmp_intval($this->gmp);
	}

	public function toString(bool $hex = false, bool $no0xHex = false): string
	{
		if(!$hex)
			return gmp_strval($this->gmp, 10);
		return ($no0xHex ? "" : "0x").gmp_strval($this->gmp, 16);
	}

	public function __toString(): string
	{
		return $this->toString();
	}

	public function raw(): GMP
	{
		return $this->gmp;
	}

	public function eq(OOGmp|int|GMP $b): bool
	{
		return gmp_cmp($this->gmp, $this->inNormalize($b)->gmp) == 0;
	}

	public function add(OOGmp|int|GMP $b): static
	{
		$static = new static();
		$static->gmp = gmp_add($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function mod(OOGmp|int|GMP $b): static
	{
		return new static(gmp_mod($this->gmp, $this->inNormalize($b)->gmp));
	}

	private function inNormalize(OOGmp|int|GMP $b): OOGmp
	{
		if($b instanceof OOGmp)
			$b = $b->gmp;
		if(is_int($b))
			$b = gmp_init($b);
		return new static($b);
	}
}
