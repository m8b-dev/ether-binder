<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Crypto\Key;

/* todo: implement wallet and wallet+rpc object */

abstract class AbstractWallet
{
	protected Key $key;

	public function key(): Key
	{
		return $this->key;
	}

	public function getKeyRaw(bool $bin = false): string
	{
		return $bin ? $this->key->toBin() : $this->key->toHex();
	}

	public function getAddress(): Address
	{
		return $this->key->toAddress();
	}
}
