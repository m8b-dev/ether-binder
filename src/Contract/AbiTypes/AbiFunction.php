<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\SolidityFunction;
use M8B\EtherBinder\Common\SolidityFunction4BytesSignature;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;

/**
 * @author DubbaThony (structure, abstraction, bugs)
 * @author gh/VOID404 (maths)
 */
class AbiFunction extends AbstractABIValue
{

	public function __construct(protected ?SolidityFunction $val)
	{}

	/**
	 * @inheritDoc
	 */
	public function isDynamic(): bool
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function encodeBin(): string
	{
		return str_pad($this->val->toBin(), 32, chr(0), STR_PAD_LEFT);
	}

	/**
	 * @inheritDoc
	 * @throws InvalidLengthException
	 * @throws EthBinderLogicException
	 */
	public function decodeBin(string &$dataBin, $globalOffset): int
	{
		// address (20b) + selector (4b) + padding
		$this->val            = new SolidityFunction(
			Address::fromBin(substr($dataBin, $globalOffset, 20)),
			SolidityFunction4BytesSignature::fromBin(substr($dataBin, $globalOffset+20, 4))
		);
		return 32;
	}

	/**
	 * @inheritDoc
	 */
	public function unwrapToPhpFriendlyVals(?array $tuplerData): SolidityFunction
	{
		return $this->val;
	}

	/**
	 * @inheritDoc
	 * @throws EthBinderLogicException
	 */
	public function __toString(): string
	{
		return $this->val == null ? "null.null" :
			$this->val->address->checksummed().".".$this->val->signature->toHex();
	}
}
