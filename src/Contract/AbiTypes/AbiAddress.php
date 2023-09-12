<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Common\Address;

class AbiAddress extends AbstractABIValue
{
	public function __construct(protected ?Address $data)
	{}

	public function isDynamic(): bool
	{
		return false;
	}

	public function encodeBin(): string
	{
		return str_repeat(chr(0), 32 - 20) . $this->data->toBin();
	}

	public function decodeBin(string &$dataBin, int $globalOffset): int
	{
		$this->data = Address::fromBin(substr($dataBin, $globalOffset+12,20));
		return 32;
	}
}