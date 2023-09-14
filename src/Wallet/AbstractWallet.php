<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Wallet;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;

/**
 * AbstractWallet serves as the base class for wallet implementations.
 * It provides basic type for wallet usage. Any new form of wallet needs to extend this class.
 *
 * @author DubbaThony
 */
abstract class AbstractWallet
{
	protected Key $key;

	/**
	 * Returns the underlying private key object stored in the wallet.
	 *
	 * @return Key The Key object.
	 */
	public function key(): Key
	{
		return $this->key;
	}

	/**
	 * Returns the raw private key in binary or hexadecimal format.
	 *
	 * @param bool $bin If true, returns in binary. Otherwise, returns in hexadecimal.
	 * @return string The raw key.
	 */
	public function getKeyRaw(bool $bin = false): string
	{
		return $bin ? $this->key->toBin() : $this->key->toHex();
	}

	/**
	 * Returns the wallet's associated Ethereum address.
	 *
	 * @return Address The Ethereum address.
	 * @throws EthBinderLogicException Logic exception
	 */
	public function getAddress(): Address
	{
		return $this->key->toAddress();
	}
}
