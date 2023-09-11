<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

class AbiBool extends AbstractABIValue
{
	public function __construct(protected ?bool $val)
	{}

	public function isDynamic(): bool
	{
		return false;
	}

	public function decodeBin(string $dataBin)
	{
		return ord($dataBin[31]) > 0;
	}


	public function encodeBin(): string
	{
		return str_repeat(chr(0), 31) . ($this->val ? chr(1) : chr(0));
	}
}
