<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Misc;

use kornrunner\Keccak;
use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Crypto\Signature;

abstract class AbstractMessage
{
	public function __construct(protected string $message, protected ?Address $from = null, protected ?Signature $sig = null)
	{}

	public function setMessage(string $message): void
	{
		if($this->message !== $message) {
			$this->message = $message;
			if($this->sig !== null)
				$this->sig = null;
		}
	}

	public function getMessage(): string
	{
		return $this->message;
	}

	public function sign(Key $key): Signature
	{
		$this->from = $key->toAddress();
		$this->sig = $key->sign($this->getMessageHash());
		$this->sig->v = $this->sig->v->mod(2)->add(27);
		return clone($this->sig);
	}

	public function isSigned(): bool
	{
		return $this->sig !== null;
	}

	public function toJson(bool $pretty): string
	{
		return json_encode(array(
			"address" => $this->from?->checksummed()??"",
			"msg"     => "0x".bin2hex($this->message),
			"sig"     => $this->sig?->toHex()??"",
			"version" => "1",
			"signer"  => "eth-binder"
		), $pretty ? JSON_PRETTY_PRINT : 0);
	}

	public function __toString(): string
	{
		return $this->toJson(true);
	}

	public function getSignature(): Signature
	{
		return clone($this->sig);
	}

	public function validateSignature(): bool
	{
		return $this->from->eq(EC::Recover($this->getMessageHash(), $this->sig->r, $this->sig->s, $this->sig->v));
	}

	private function getMessageHash(): Hash
	{
		$t = $this->preProcessMessage();
		return Hash::fromBin(Keccak::hash($t, 256, true));
	}

	abstract protected function preProcessMessage(): string;
}