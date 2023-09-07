<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Utils\OOGmp;

class AbiTuple extends AbstractABIValue implements \ArrayAccess
{
	/** @var AbstractABIValue[] $inner */
	protected array $inner = [];

	public function isDynamic(): bool
	{
		foreach($this->inner AS $val) {
			if($val->isDynamic())
				return true;
		}
		return false;
	}

	public function encodeBin(): string
	{
		$tails = [];
		$totalHeadLen = 0;
		foreach($this->inner AS $val) {
			$tails[] = $this->tail($val);
			$totalHeadLen += $this->headLen($val);
		}

		$headFn = function(int $index, AbstractABIValue $val) use($tails, $totalHeadLen) : string {
			if($val->isDynamic()) {
				$pointer = $totalHeadLen;
				for($i = 0; $i < $index ; $i++)
					$pointer += strlen($tails[$i]);
				return (new OOGmp($pointer))->toBin(32);
			}
			return $val->encodeBin();
		};

		$heads = "";
		for($i = 0; $i < count($this->inner); $i++)
		{
			$heads .= $headFn($i, $this->inner[$i]);
		}

		return $heads . implode("", $tails);
	}

	protected function tail(AbstractABIValue $val): string
	{
		if($val->isDynamic()) {
			return $val->encodeBin();
		}
		return "";
	}

	protected function headLen(AbstractABIValue $val): int
	{
		if($val->isDynamic()) {
			return 32;
		}
		return strlen($val->encodeBin());
	}

	public function __toString(): string
	{
		$ret = "(";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").(string)$inr;
		}
		$ret .= ")";
		return $ret;
	}

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->inner[$offset]);
	}

	public function offsetGet(mixed $offset): AbstractABIValue
	{
		return $this->inner[$offset];
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if($offset === null) {
			// append operator
			$offset = count($this->inner);
		}
		if(!is_int($offset))
			throw new EthBinderLogicException("ABI values do support only integer offsets");
		if($value instanceof AbstractABIValue) {
			$this->inner[$offset] = $value;
		} else {
			throw new EthBinderLogicException("cannot set offset of non AbstractABIValue in ABITuple");
		}
	}

	public function offsetUnset(mixed $offset): void
	{
		unset($this->inner[$offset]);
	}
}
