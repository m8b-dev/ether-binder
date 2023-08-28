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
use M8B\EtherBinder\Utils\OOGmp;

class Key
{
	private KeyPair $key;

	protected function __construct(#[\SensitiveParameter] private string $keyHex)
	{
		if(str_starts_with($this->keyHex, "0x"))
			$this->keyHex = substr($this->keyHex, 2);
		$this->key = EC::keyFromPrivate($this->keyHex, "hex");
	}

	public static function fromHex(#[\SensitiveParameter] string $keyHex): static
	{
		return new self($keyHex);
	}

	public function toHex(): string
	{
		return "0x".$this->keyHex;
	}

	public function toAddress(): Address
	{
		return Address::fromBin(
			substr(
				Keccak::hash(hex2bin(
				substr($this->key->getPublic(false, "hex"), 2)
				), 256, true),
			32-20)
		);
	}

	public static function fromBin(string $bin): static
	{
		return new static(bin2hex($bin));
	}

	public function toBin(): string
	{
		return hex2bin($this->keyHex);
	}

	public function sign(Hash $hash): Signature
	{
		$got = $this->key->sign($hash->toHex(false), ["canonical" => true]);
		$sig = new Signature();
		$sig->r = new OOGmp($got->r->toString(16), 16);
		$sig->s = new OOGmp($got->s->toString(16), 16);
		$sig->v = new OOGmp($got->recoveryParam);
		return $sig;
	}
}