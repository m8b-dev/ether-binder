<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Utils\OOGmp;

class AbiArrayUnknownLength extends AbiArrayKnownLength
{

	public function __construct()
	{
		parent::__construct(-1);
	}

	public function isDynamic(): bool
	{
		return true;
	}

	public function encodeBin(): string
	{
		$data = (new OOGmp(count($this->inner)))->toBin(32);
		return $data . parent::encodeBin();
	}

	public function __toString(): string
	{
		$ret = "u[";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").(string)$inr;
		}
		$ret .= "]";
		return $ret;
	}
}