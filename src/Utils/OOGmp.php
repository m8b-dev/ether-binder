<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Utils;

use GMP;
use M8B\EtherBinder\Common\HashSerializable;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidHexException;

/**
 * OOGmp is a utility class that wraps PHP's GMP library for working with arbitrary-size integers.
 * It provides an easy-to-use, object-oriented API for Ethereum-related operations and offers a chainable
 * arithmetic operations API.
 *
 * Arithmetic Functions:
 * The class provides basic arithmetic operations like addition (`add`), subtraction (`sub`), multiplication (`mul`), and division (`div`).
 * These methods support automatic type normalization, allowing you to pass in `OOGmp|int|GMP` as arguments.
 *
 * Comparison Functions:
 * Standard comparison operations are included, such as `eq` (equals), `lt` (less than), `gt` (greater than), etc.
 * Aliases are also available, like `eq` - `equal`. These functions also support automatic type normalization.
 *
 * @author DubbaThony
 */
class OOGmp implements HashSerializable
{
	private GMP $gmp;

	/**
	 * Initializes a new OOGmp object.
	 *
	 * @param null|string|GMP $number The initial number value as null, string, or GMP object.
	 * @param int|null $base The base for string input. Defaults attempts to guess, if $number has 0x prefix, it defaults to 16,
	 *                       otherwise if it has any of a-f characters, it defaults to 16, otherwise it defaults to 10.
	 *                       Be very cautions when relying on that detection as non-0x-prefixed hex that happens to not
	 *                       have any a-f will be mistreated as base = 10.
	 */
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
		// RPCs like to return "0x" for 0x0.
		if($number == "0x" || $number == "") {
			$this->gmp = gmp_init(0, 10);
			return;
		}

		if($base !== null) {
			if($base == 16 && str_starts_with($number, "0x"))
				$number = substr($number, 2);
			$this->gmp = gmp_init($number, $base);
		} else {
			if(str_starts_with($number, "0x"))
				$this->gmp = gmp_init(substr($number, 2), 16);

			elseif(ctype_digit(ltrim($number, "-")))
				$this->gmp = gmp_init($number, 10);

			else
				$this->gmp = gmp_init($number, 16);
		}
	}

	/**
	 * Wraps a raw GMP object into an OOGmp instance.
	 *
	 * @param GMP $raw The GMP object to be wrapped.
	 * @return OOGmp The new OOGmp instance wrapping the given GMP object.
	 */
	public static function wrap(GMP $raw): static
	{
		$static      = new static();
		$static->gmp = $raw;
		return $static;
	}

	/**
	 * Converts the internal GMP number to an integer.
	 *
	 * @return int The integer representation of the GMP number.
	 * @throws EthBinderRuntimeException If the GMP number is larger than PHP_INT_MAX.
	 */
	public function toInt(): int
	{
		if(gmp_cmp($this->gmp, PHP_INT_MAX) > 0) {
			throw new EthBinderRuntimeException("number is bigger than PHP_INT_MAX and toInt is not available");
		}
		return gmp_intval($this->gmp);
	}

	/**
	 * Converts the internal GMP number to a string.
	 *
	 * @param bool $hex If true, returns a hexadecimal string. Otherwise, returns a decimal string.
	 * @param bool $no0xHex If true and $hex is true, omits the "0x" prefix.
	 * @param int|null $lpad0 Number of zeros to pad on the left.
	 * @return string The string representation of the GMP number.
	 */
	public function toString(bool $hex = false, bool $no0xHex = false, ?int $lpad0 = null): string
	{
		if(!$hex)
			return gmp_strval($this->gmp, 10);
		if($this->ge(0)) {
			if($lpad0 === null)
				return ($no0xHex ? "" : "0x").gmp_strval($this->gmp, 16);

			return ($no0xHex ? "" : "0x").str_pad(gmp_strval($this->gmp, 16), $lpad0, "0", STR_PAD_LEFT);
		} else {
			// we are < 0, so instead of for example 10 = 0x0a, we need to output 0xf6
			if($lpad0 === null)
				$bitSize = (strlen(gmp_strval($this->gmp, 16)) + 1) * 4;
			else
				$bitSize = $lpad0 * 4;
			$twosComplement = gmp_add(gmp_pow(2, $bitSize), $this->gmp);
			return ($no0xHex ? "" : "0x").gmp_strval($twosComplement, 16);
		}
	}

	/**
	 * Converts the internal GMP number to a binary string.
	 *
	 * @param int|null $lpad0 Number of zeros to pad on the left.
	 * @return string The binary string representation of the GMP number.
	 * @throws InvalidHexException
	 */
	public function toBin(?int $lpad0 = null): string
	{
		$bin = Functions::hex2bin($this->toString(true, true));
		return $lpad0 === null ? $bin
			: str_pad($bin, $lpad0, chr(0), STR_PAD_LEFT);
	}

	public function __toString(): string
	{
		return $this->toString();
	}

	/**
	 * Returns the raw GMP object.
	 *
	 * @return GMP The internal GMP object.
	 */
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
		$static      = new static();
		$static->gmp = gmp_add($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function sub(OOGmp|int|GMP $b): static
	{
		$static      = new static();
		$static->gmp = gmp_sub($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function mul(OOGmp|int|GMP $b): static
	{
		$static      = new static();
		$static->gmp = gmp_mul($this->gmp, $this->inNormalize($b)->gmp);
		return $static;
	}

	public function div(OOGmp|int|GMP $b): static
	{
		$static      = new static();
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

	/**
	 * Alias to toString() to fulfil HashSerializable interface
	 *
	 * @return string
	 */
	public function toHex(): string
	{
		return $this->toString(true, false);
	}

	/**
	 * Static alias to constructor to fulfil HashSerializable interface
	 *
	 * @param string $hex
	 * @return OOGmp
	 */
	public static function fromHex(string $hex): static
	{
		return new static($hex, 16);
	}

	/**
	 * Static alias to constructor to fulfil HashSerializable interface
	 *
	 * @param string $bin
	 * @return OOGmp
	 */
	public static function fromBin(string $bin): static
	{
		return static::fromHex(bin2hex($bin));
	}
}
