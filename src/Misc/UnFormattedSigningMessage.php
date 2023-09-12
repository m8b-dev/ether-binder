<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Misc;

/**
 * UnFormattedSigningMessage is a subclass of AbstractSigningMessage that doesn't add any formatting or 'magic bytes' to the message.
 * This class simply uses the raw message for signing purposes.
 *
 * @author DubbaThony
 */
class UnFormattedSigningMessage extends AbstractSigningMessage
{
	/**
	 * Preprocess the message without adding any formatting.
	 *
	 * @return string The original, untouched message.
	 */
	protected function preProcessMessage(): string
	{
		return $this->message;
	}
}
