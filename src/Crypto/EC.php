<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Crypto;

use Elliptic\EC\KeyPair;
use kornrunner\Keccak;
use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Utils\OOGmp;

/**
 * EC is a wrapper class for Elliptic Curve operations, specifically secp256k1 which is one specifically used for
 * Ethereum. Internally it statically caches EC object, as it's instantiation has high runtime cost.
 *
 * @author DubbaThony
 */
class EC
{
	private static \Elliptic\EC $ec;

	/**
	 * Initializes the Elliptic Curve context. Safe to call multiple times.
	 */
	private static function init(): void
	{
		static $done = false;
		if(!$done)
			self::$ec = new \Elliptic\EC("secp256k1");
		$done = true;
	}

	/**
	 * Recovers the Ethereum Address from a hash and signature parameters.
	 *
	 * @param Hash $hash Hash object to be verified.
	 * @param OOGmp $r The 'r' part of the signature.
	 * @param OOGmp $s The 's' part of the signature.
	 * @param OOGmp $v The 'v' part of the signature.
	 * @return Address The Ethereum address.
	 * @throws EthBinderRuntimeException
	 * @throws EthBinderLogicException
	 */
	public static function Recover(Hash $hash, OOGmp $r, OOGmp $s, OOGmp $v): Address
	{
		self::init();

		try {
			$pubK = self::$ec->recoverPubKey(
				$hash->toHex(false),
				[
					"r" => $r->toString(true, true),
					"s" => $s->toString(true, true)
				],
				$v->toInt());
			$pubKBin = hex2bin($pubK->encode("hex"));
			$pubKBin = substr($pubKBin, 1);
			$hash = Keccak::hash($pubKBin, 256, true);
			return Address::fromBin(substr($hash, 32 - 20));

		} catch(InvalidLengthException $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		} catch(\Exception $e) {
			throw new EthBinderRuntimeException("cryptographic error", $e->getCode(), $e);
		}
	}

	/**
	 * Creates a KeyPair object from a private key.
	 *
	 * @param string $key Hex-encoded private key.
	 * @return KeyPair Elliptic Curve KeyPair.
	 */
	public static function keyFromPrivate(#[\SensitiveParameter] string $key): KeyPair
	{
		self::init();
		return self::$ec->keyFromPrivate($key, "hex");
	}

	/**
	 * Returns the underlying cached Elliptic\EC context.
	 *
	 * @return \Elliptic\EC The Elliptic\EC context.
	 */
	public static function EC(): \Elliptic\EC
	{
		self::init();
		return self::$ec;
	}
}
