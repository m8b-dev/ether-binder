<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Utils\OOGmp;

/**
 * @author DubbaThony (structure, abstraction, bugs)
 * @author gh/VOID404 (maths)
 */
class AbiBytes extends AbstractABIValue
{
	protected bool $dynamic;
	public function __construct(protected ?string $data, protected int $size)
	{
		if($this->size == 0) {
			$this->dynamic = true;
		} else {
			$this->dynamic = false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function isDynamic(): bool
	{
		return $this->dynamic;
	}

	/**
	 * @inheritDoc
	 */
	public function decodeBin(string &$dataBin, int $globalOffset): int
	{
		if(!$this->isDynamic()) {
			$this->data = substr($globalOffset, $this->size);
			return 32;
		}
		$length         = (new OOGmp(bin2hex(substr($dataBin, $globalOffset, 32)), 16))->toInt();
		$this->data     = substr($dataBin, $globalOffset+32, $length);
		$actualDataRead = (int)(ceil($length/32)*32);
		return $actualDataRead + 32;
	}

	/**
	 * @inheritDoc
	 */
	public function encodeBin(): string
	{
		if(!$this->dynamic)
			return str_pad($this->data, 32, chr(0), STR_PAD_RIGHT);
		$slots = ceil(strlen($this->data) / 32);

		return (new OOGmp(strlen($this->data)))->toBin(32)
			.str_pad($this->data, 32 * $slots, chr(0), STR_PAD_RIGHT);
	}

	/**
	 * @inheritDoc
	 */
	public function unwrapToPhpFriendlyVals(?array $tuplerData): string
	{
		return $this->data;
	}

	/**
	 * @inheritDoc
	 */
	public function __toString(): string
	{
		return "0x".bin2hex($this->data ?? "");
	}
}
