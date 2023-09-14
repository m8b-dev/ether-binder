<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Misc;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;

/**
 * InnerHashedMessage is a subclass of AbstractSigningMessage designed to handle messages where the content is hashed internally.
 * The message gets prepended with "\x19Ethereum Signed Message:\n32" followed by the Keccak-256 hash of the message.
 *
 * @author DubbaThony
 */
class InnerHashedMessage extends AbstractSigningMessage
{
	/**
	 * @throws EthBinderLogicException
	 */
	protected function preProcessMessage(): string
	{
		try {
			return chr(0x19) . "Ethereum Signed Message:\n32"
				. Keccak::hash($this->message, 256, true);
		} catch(Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
	}
}