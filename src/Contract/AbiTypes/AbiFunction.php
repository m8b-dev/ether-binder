<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Common\SolidityFunction;
use M8B\EtherBinder\Contract\AbiTypes\AbstractABIValue;

class AbiFunction extends AbstractABIValue
{

	public function __construct(protected SolidityFunction $val)
	{}

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return str_pad($this->val->toBin(), 32, chr(0), STR_PAD_LEFT);
	}
}