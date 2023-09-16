<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Misc;

use Exception;
use kornrunner\Keccak;
use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Crypto\EC;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Crypto\Signature;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;

/**
 * AbstractSigningMessage is class for handling Ethereum signed messages, regardless of specific formatting which is
 * offloaded to class extending AbstractSigningMessage. This allows for easy implementation of multiple signing formats
 * offered by different wallets, usually small variations of "\x19Ethereum Signed Message:\n(len)(message)"
 *
 * @author DubbaThony
 */
abstract class AbstractSigningMessage
{
	/**
	 * Initializes the object with the message, from address and signature.
	 *
	 * @param string $message The message to be signed.
	 * @param Address|null $from Ethereum address sending the message. Optional.
	 * @param Signature|null $sig Existing signature. Optional.
	 */
	public function __construct(protected string $message, protected ?Address $from = null, protected ?Signature $sig = null)
	{}

	/**
	 * Set the message. This will invalidate and remove the current signature if message is different from currently set
	 * and if signature is present
	 *
	 * @param string $message The new message.
	 */
	public function setMessage(string $message): void
	{
		if($this->message !== $message) {
			$this->message = $message;
			if($this->sig !== null)
				$this->sig = null;
		}
	}

	/**
	 * Get the current message.
	 *
	 * @return string The current message.
	 */
	public function getMessage(): string
	{
		return $this->message;
	}

	/**
	 * Sign the message.
	 *
	 * @param Key $key Private key for signing.
	 * @return Signature Clone of generated signature.
	 * @throws EthBinderLogicException
	 */
	public function sign(Key $key): Signature
	{
		$this->from   = $key->toAddress();
		$this->sig    = $key->sign($this->getMessageHash());
		$this->sig->v = $this->sig->v->mod(2)->add(27);
		return clone($this->sig);
	}

	/**
	 * Check if message is signed.
	 *
	 * @return bool True if message has been signed, false otherwise.
	 */
	public function isSigned(): bool
	{
		return $this->sig !== null;
	}

	/**
	 * Convert object state to JSON string, producing JSON object similar to MEW and other wallets that support signing
	 *
	 * @param bool $pretty Use JSON pretty print option if true.
	 * @return string The object state as a JSON string.
	 * @throws EthBinderLogicException
	 */
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

	/**
	 * Default to string behaviour is pretty printed JSON.
	 *
	 * @return string Pretty printed JSON
	 * @throws EthBinderLogicException
	 * @see self::toJson
	 */
	public function __toString(): string
	{
		return $this->toJson(true);
	}

	/**
	 * Retrieve the signature object.
	 *
	 * @return Signature The clone of existing signature.
	 */
	public function getSignature(): Signature
	{
		return clone($this->sig);
	}

	/**
	 * Validate if the existing signature matches address it claims to be from.
	 *
	 * @return bool True if signature is valid, false otherwise.
	 * @throws EthBinderLogicException
	 * @throws EthBinderRuntimeException
	 */
	public function validateSignature(): bool
	{
		return $this->from->eq(EC::Recover($this->getMessageHash(), $this->sig->r, $this->sig->s, $this->sig->v));
	}

	/**
	 * @throws EthBinderLogicException
	 */
	private function getMessageHash(): Hash
	{
		$t = $this->preProcessMessage();
		try {
			return Hash::fromBin(Keccak::hash($t, 256, true));
		} catch(InvalidLengthException|Exception $e) {
			throw new EthBinderLogicException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Preprocess the message before hashing, for example adding signing "magic bytes" such as "\x19Ethereum Signed Message" etc.
	 * This allows for multiple formats support
	 *
	 * @return string The pre-processed message.
	 */
	abstract protected function preProcessMessage(): string;
}