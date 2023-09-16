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
class AbiArrayUnknownLength extends AbiArrayKnownLength
{

	public function __construct(?AbstractABIValue $children = null)
	{
		parent::__construct(-1, $children);
	}

	/**
	 * @inheritDoc
	 */
	public function isDynamic(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function encodeBin(): string
	{
		$data = (new OOGmp(count($this->inner)))->toBin(32);
		return $data . parent::encodeBin();
	}

	/**
	 * @inheritDoc
	 */
	public function __toString(): string
	{
		$ret = "u[";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").$inr;
		}
		$ret .= "]";
		return $ret;
	}

	/**
	 * @inheritDoc
	 */
	public function decodeBin(string &$dataBin, int $globalOffset): int
	{
		$this->length = (new OOGmp(bin2hex(substr($dataBin, $globalOffset, 32)), 16))->toInt();
		for($i = 0; $i < $this->length; $i++)
			$this->inner[$i] = clone($this->emptyType);

		return parent::decodeBin($dataBin, $globalOffset + 32) + 32;
	}
}
