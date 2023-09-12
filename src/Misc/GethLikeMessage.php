<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Misc;

/**
 * GethLikeMessage is a subclass of AbstractSigningMessage that pre-processes messages in a way that Geth does.
 * The message gets prepended with "\x19Ethereum Signed Message:\n" followed by the message length, to align with
 * Geth's signing method.
 *
 * @author DubbaThony
 */
class GethLikeMessage extends AbstractSigningMessage
{
	protected function preProcessMessage(): string
	{
		return chr(0x19)."Ethereum Signed Message:\n"
			.strlen($this->message)
			.$this->message;
	}
}
