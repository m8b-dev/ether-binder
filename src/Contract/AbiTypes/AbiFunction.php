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
use M8B\EtherBinder\Contract\AbiTypes\AbstractABIValue;

class AbiFunction extends AbstractABIValue
{

	public function __construct(protected ?SolidityFunction $val)
	{}

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return str_pad($this->val->toBin(), 32, chr(0), STR_PAD_LEFT);
	}

	public function decodeBin(string &$dataBin, $globalOffset): int
	{
		$this->val = new SolidityFunction();
		// padding + address (20b) + selector (4b)
		$this->val->address = Address::fromBin(substr($dataBin, $globalOffset+0, 20));
		$this->val->signature = SolidityFunction4BytesSignature::fromBin(substr($dataBin, $globalOffset+20, 4));
		return 32;
	}
}
