<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use kornrunner\Keccak;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;

class SolidityFunction4BytesSignature
{
	public function __construct(protected string $fourBytes)
	{
		if(strlen($this->fourBytes) != 4)
			throw new EthBinderArgumentException("function signature is exactly 4 bytes");
	}

	public static function fromSignature(string $functionSignature): static
	{
		return new static(substr(Keccak::hash($functionSignature, 256, true), 0, 4));
	}

	public function toBin(): string
	{
		return $this->fourBytes;
	}
}
