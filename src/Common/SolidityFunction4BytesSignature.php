<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Common;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;

/**
 * SolidityFunction4BytesSignature is container class for a 4-byte function signature in Solidity.
 *
 * @author DubbaThony
 */
class SolidityFunction4BytesSignature extends Hash
{
	const dataSizeBytes = 4;

	/**
	 * Creates a new SolidityFunction4BytesSignature instance from a full function signature.
	 *
	 * @param string $functionSignature The complete function signature.
	 * @return static A new instance of SolidityFunction4BytesSignature.
	 * @throws EthBinderLogicException
	 */
	public static function fromSignature(string $functionSignature): static
	{
		try {
			return new static(substr(Keccak::hash($functionSignature, 256, true), 0, 4));
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
	}
}
