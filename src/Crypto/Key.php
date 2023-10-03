<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Crypto;

use Elliptic\EC\KeyPair;
use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Utils\OOGmp;
use SensitiveParameter;

/**
 * Key is a representation for raw Ethereum private key. It contains essential utilities for its usage and is used in
 * EthBinder as raw private key.
 *
 * @author DubbaThony
 */
class Key
{
	private ?Address $addr;
	private KeyPair $key;

	/**
	 * @param string $keyHex Hexadecimal string of the private key.
	 */
	protected function __construct(#[SensitiveParameter] private string $keyHex)
	{
		if(str_starts_with($this->keyHex, "0x"))
			$this->keyHex = substr($this->keyHex, 2);
		$this->key  = EC::keyFromPrivate($this->keyHex);
		$this->addr = null;
	}

	/**
	 * Initializes from a hexadecimal string.
	 *
	 * @param string $keyHex Hexadecimal string of the private key.
	 * @return static Instance of Key class.
	 */
	public static function fromHex(#[SensitiveParameter] string $keyHex): static
	{
		return new self($keyHex);
	}

	/**
	 * Returns the hexadecimal representation of the key, prefixed with '0x'.
	 *
	 * @return string Hexadecimal string of the key.
	 */
	public function toHex(): string
	{
		return "0x".$this->keyHex;
	}

	/**
	 * Generates an Ethereum Address based on the key. Internally caches the address to avoid wasting time on hashing.
	 *
	 * @return Address The Ethereum address.
	 * @throws EthBinderLogicException
	 */
	public function toAddress(): Address
	{
		if($this->addr !== null)
			return $this->addr;
		try {
			$this->addr = Address::fromBin(
				substr(
					Keccak::hash(hex2bin(
						substr($this->key->getPublic(false, "hex"), 2)
					), 256, true),
					32 - 20)
			);
		} catch(Exception|InvalidLengthException $e) {
			throw new EthBinderLogicException("got invalid length exception with const length", $e->getCode(), $e);
		}
		return $this->addr;
	}

	/**
	 * Initializes from binary of the key.
	 *
	 * @param string $bin Binary string of the key.
	 */
	public static function fromBin(string $bin): static
	{
		return new static(bin2hex($bin));
	}

	/**
	 * Returns the binary of the key.
	 *
	 * @return string Binary string of the key.
	 */
	public function toBin(): string
	{
		return hex2bin($this->keyHex);
	}

	/**
	 * Signs arbitrary hash using the private key.
	 *
	 * @param Hash $hash Hash to be signed.
	 * @return Signature The resulting Ethereum signature.
	 */
	public function sign(Hash $hash): Signature
	{
		$got    = $this->key->sign($hash->toHex(false), ["canonical" => true]);
		$sig    = new Signature();
		$sig->r = new OOGmp($got->r->toString(16), 16);
		$sig->s = new OOGmp($got->s->toString(16), 16);
		$sig->v = new OOGmp($got->recoveryParam);
		return $sig;
	}

	/**
	 * Generates new random key using openssl for random bytes. Triggers E_USER_WARNING if weak entropy was used.
	 *
	 * @return static
	 */
	public static function generate(): static
	{
		$strong = false;
		$key = openssl_random_pseudo_bytes(32, $strong);
		if(!$strong) {
			trigger_error(E_USER_WARNING, "openssl_random_pseudo_bytes() used weak entropy");
		}

		return new static(bin2hex($key));
	}
}
