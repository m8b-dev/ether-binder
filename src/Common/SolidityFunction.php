<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

class SolidityFunction
{
	public Address $address;
	public SolidityFunction4BytesSignature $signature;

	public function toBin(): string
	{
		return $this->address->toBin() . $this->signature->toBin();
	}
}
