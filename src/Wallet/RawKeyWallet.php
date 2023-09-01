<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

use M8B\EtherBinder\Crypto\Key;

class RawKeyWallet extends AbstractWallet
{
	public function __construct(#[\SensitiveParameter] string|Key $key)
	{
		if($key instanceof Key)
			$this->key = $key;
		else
			$this->key = Key::fromHex($key);
	}
}