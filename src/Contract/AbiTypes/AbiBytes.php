<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Utils\OOGmp;

class AbiBytes extends AbstractABIValue
{
	protected bool $dynamic;
	public function __construct(protected string $data, protected int $size)
	{
		if($this->size == 0) {
			$this->dynamic = true;
		} else {
			$this->dynamic = false;
		}
	}

	public function isDynamic(): bool
	{
		return $this->dynamic;
	}

	public function encodeBin(): string
	{
		if(!$this->dynamic)
			return str_pad($this->data, 32, chr(0), STR_PAD_LEFT);
		$slots = ceil(strlen($this->data) / 32);

		return (new OOGmp(strlen($this->data)))->toBin(32)
			.str_pad($this->data, 32 * $slots, chr(0), STR_PAD_LEFT);
	}
}
