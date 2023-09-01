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

	public function toString(bool $hex = false, bool $no0xHex = false, ?int $lpad0 = null): string
	{
		if(!$hex)
			return gmp_strval($this->gmp, 10);
		if($lpad0 === null)
			return ($no0xHex ? "" : "0x").gmp_strval($this->gmp, 16);

		return ($no0xHex ? "" : "0x").str_pad(gmp_strval($this->gmp, 16), $lpad0, "0", STR_PAD_LEFT);
	}

	public function toBin(?int $lpad0 = null): string
	{
		$hex = $this->toString(true, true);
		if(strlen($hex) % 2 != 0)
			$hex = "0".$hex;
		$bin = hex2bin($hex);
		return $lpad0 === null ? $bin
			: str_pad($bin, $lpad0, chr(0), STR_PAD_LEFT);
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

	public function gt(OOGmp|int|GMP $b): bool
	{
		return gmp_cmp($this->gmp, $this->inNormalize($b)->gmp) > 0;
	}

	public function lt(OOGmp|int|GMP $b): bool
	{
		return gmp_cmp($this->gmp, $this->inNormalize($b)->gmp) < 0;
	}

	public function ge(OOGmp|int|GMP $b): bool
	{
		return gmp_cmp($this->gmp, $this->inNormalize($b)->gmp) >= 0;
	}

	public function le(OOGmp|int|GMP $b): bool
	{
		return gmp_cmp($this->gmp, $this->inNormalize($b)->gmp) <= 0;
	}

	public function greaterThan(OOGmp|int|GMP $b): bool
	{
		return $this->gt($b);
	}

	public function lessThan(OOGmp|int|GMP $b): bool
	{
		return $this->lt($b);
	}

	public function lessOrEqual(OOGmp|int|GMP $b): bool
	{
		return $this->le($b);
	}

	public function greaterOrEqual(OOGmp|int|GMP $b): bool
	{
		return $this->ge($b);
	}

	public function equal(OOGmp|int|GMP $b): bool
	{
		return $this->eq($b);
	}

	public function add(OOGmp|int|GMP $b): static
	{
		$static = new static();
		$static->gmp = gmp_add($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function sub(OOGmp|int|GMP $b): static
	{
		$static = new static();
		$static->gmp = gmp_sub($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function mul(OOGmp|int|GMP $b): static
	{
		$static = new static();
		$static->gmp = gmp_mul($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function div(OOGmp|int|GMP $b): static
	{
		$static = new static();
		$static->gmp = gmp_div($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function max(OOGmp|int|GMP $b): static
	{
		$b = $this->inNormalize($b);
		if($this->ge($b))
			return $this->inNormalize($this);
		return $b;
	}

	public function min(OOGmp|int|GMP $b): static
	{
		$b = $this->inNormalize($b);
		if($this->le($b))
			return $this->inNormalize($this);
		return $b;
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
