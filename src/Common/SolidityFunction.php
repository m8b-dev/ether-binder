<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use M8B\EtherBinder\Exceptions\EthBinderLogicException;

/**
 * SolidityFunction represents a Solidity function type, consisting with its address and 4-byte signature, uniquely
 * pointing to address and selector.
 *
 * @author DubbaThony
 */
class SolidityFunction
{
	public Address $address;
	public SolidityFunction4BytesSignature $signature;

	/**
	 * Constructs new instance using Address
	 *
	 * @throws EthBinderLogicException
	 */
	public function __construct(Address $address, string|SolidityFunction4BytesSignature $sig) {
		$this->address = $address;
		if(is_string($sig))
			$sig = SolidityFunction4BytesSignature::fromSignature($sig);
		$this->signature = $sig;
	}

	/**
	 * Serializes transaction to binary blob
	 *
	 * @return string binary blob
	 */
	public function toBin(): string
	{
		return $this->address->toBin() . $this->signature->toBin();
	}
}
