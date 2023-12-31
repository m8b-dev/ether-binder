<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use ArrayAccess;
use M8B\EtherBinder\Contract\AbstractTuple;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * @author DubbaThony (structure, abstraction, bugs)
 * @author gh/VOID404 (maths)
 */
class AbiTuple extends AbstractABIValue implements ArrayAccess
{
	/** @var AbstractABIValue[] $inner */
	protected array $inner = [];

	/**
	 * @inheritDoc
	 */
	public function isDynamic(): bool
	{
		foreach($this->inner AS $val) {
			if($val->isDynamic())
				return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
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

	/**
	 * @inheritDoc
	 * @throws EthBinderRuntimeException
	 */
	public function decodeBin(string &$dataBin, int $globalOffset): int
	{
		$tailSize = 0;
		$headSize = 0;

		foreach($this->inner AS $inner) {
			if($inner->isDynamic()) {
				$tailPointer = (new OOGmp(bin2hex(substr($dataBin, $headSize+$globalOffset, 32)), 16))->toInt();
				$headSize   += 32;
				$tailSize   += $inner->decodeBin($dataBin, $globalOffset + $tailPointer);
			} else {
				$headSize += $inner->decodeBin($dataBin, $headSize+$globalOffset);
			}
		}

		return $tailSize + $headSize;
	}


	/**
	 * @throws InvalidHexException
	 */
	protected function tail(AbstractABIValue $val): string
	{
		if($val->isDynamic()) {
			return $val->encodeBin();
		}
		return "";
	}

	/**
	 * @throws InvalidHexException
	 */
	protected function headLen(AbstractABIValue $val): int
	{
		if($val->isDynamic()) {
			return 32;
		}
		return strlen($val->encodeBin());
	}

	/**
	 * @inheritDoc
	 */
	public function __toString(): string
	{
		$ret = "(";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").$inr;
		}
		$ret .= ")";
		return $ret;
	}

	public function __clone() {
		for($i = 0; $i < count($this->inner); $i++)
			$this->inner[$i] = clone $this->inner[$i];
	}

	/**
	 * @inheritDoc
	 */
	public function unwrapToPhpFriendlyVals(?array $tuplerData): array|AbstractTuple
	{
		$o = [];
		if(
			   $tuplerData !== null         // There is typed tuple logic needed
			&& !empty($tuplerData["tuple"]) // I am not root-level tuple, which is not solidity struct
		) {
			$o          = new $tuplerData["tuple"](); // Therefore, I am solidity struct
			$tuplerData = $tuplerData["children"];
		}
		foreach($this->inner as $k => $item) {
			if($tuplerData === null)
				$o[] = $item->unwrapToPhpFriendlyVals(null);
			else {
				$o[] = $item->unwrapToPhpFriendlyVals($tuplerData[$k]);
			}
		}

		return $o;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->inner[$offset]);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet(mixed $offset): AbstractABIValue
	{
		return $this->inner[$offset];
	}

	/**
	 * @inheritDoc
	 * @throws EthBinderLogicException
	 */
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

	/**
	 * @inheritDoc
	 */
	public function offsetUnset(mixed $offset): void
	{
		unset($this->inner[$offset]);
	}
}
