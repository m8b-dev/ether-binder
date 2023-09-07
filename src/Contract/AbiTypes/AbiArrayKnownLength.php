<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract\AbiTypes;

use M8B\EtherBinder\Utils\OOGmp;

class AbiArrayKnownLength extends AbiTuple
{
	public function __construct(protected int $length)
	{}

	public function __toString(): string
	{
		$ret = "k[";
		foreach($this->inner AS $k => $inr) {
			$ret .= ($k > 0 ? ",":"").(string)$inr;
		}
		$ret .= "]";
		return $ret;
	}
}
