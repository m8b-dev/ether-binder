<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Crypto;

use M8B\EtherBinder\Utils\OOGmp;

/**
 * Signature is a class for handling and representing Ethereum signatures.
 *
 * @author DubbaThony
 */
class Signature
{
	public OOGmp $v;
	public OOGmp $r;
	public OOGmp $s;

	/**
	 * Converts the signature to a hexadecimal string. The output has a constant length of 66 nibbles, ordered 'r',
	 * 's', and 'v'. The output is NOT '0x' prefixed.
	 *
	 * @return string The signature as a hexadecimal string.
	 */
	public function toHex(): string
	{
		return $this->r->toString(true, true, 32)
			 . $this->s->toString(true, true, 32)
			 . $this->v->toString(true, true, 2);
	}

	/**
	 * Converts the signature to a binary string. The output has a constant length of 33 bytes, ordered 'r',
	 * 's', and 'v'.
	 *
	 * @return string The signature as a binary blob string.
	 */
	public function toBin(): string
	{
		return hex2bin($this->toHex());
	}
}
