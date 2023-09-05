<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Crypto;

use M8B\EtherBinder\Utils\OOGmp;

class Signature
{
	public OOGmp $v;
	public OOGmp $r;
	public OOGmp $s;

	public function toHex(): string
	{
		return $this->r->toString(true, true, 32)
			 . $this->s->toString(true, true, 32)
			 . $this->v->toString(true, true, 2);
	}

	public function toBin(): string
	{
		return hex2bin($this->toHex());
	}
}
