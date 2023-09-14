<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\LegacyTransaction;
use M8B\EtherBinder\Common\LondonTransaction;
use M8B\EtherBinder\Common\Receipt;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\UnexpectedUnsignedException;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\OOGmp;
use SensitiveParameter;

/**
 * AbstractContract serves as the base class for all Ethereum smart contract bindings.
 * It handles various tasks such as transaction management and encoding/decoding of ABI parameters
 * of underlying contract binding.
 *
 * @author DubbaThony
 */
abstract class AbstractContract
{
	/**
	 * @var int $transactionFeesPercentageBump is to be directly set from outside. It increases gas price by this amount
	 *  of percent. If set to 0, the bare minimum fees will be applied. If set to negative, probably insufficient fees
	 *  will be set, but still there is possibility of transaction to go through. If set to for example 20, the fees will
	 *  be 120% of what is estimated to be bare minimum
	 */
	public static int $transactionFeesPercentageBump = 0;

	/**
	 * @var bool $noSend is to be directly set from outside. If set to true, transactions will be created but not sent.
	 * Useful for gas estimations to be presented to user, or inspecting transaction before sending it.
	 */
	public bool $noSend = false;

	/**
	 * Constructs a new instance of the underlying binding. This assumes the contract is deployed. If it's not the case,
	 * deploy it using static functions.
	 *
	 * @param AbstractRPC $rpc The RPC interface used for interacting with Ethereum.
	 * @param Address $contractAddress The address of the deployed smart contract.
	 * @param ?Key $key Optional, nullable private key for signing transactions. Without it transactions will be returned
	 *                  unsigned and unsent, and calls/estimations will be with from field $fallbackFrom.
	 * @param ?Address $fallbackFrom Optional, nullable address to be used if the private key is not available for calls
	 *                               from field and gas estimations. If this field is null, Address::NULL() will be used.
	 *                               If you intend to sign the transaction, it's good idea to set this field to address
	 *                               of private key, since contracts may use msg.sender which may change if transaction
	 *                               reverts, or change which branches get executing resulting in bogus gas estimations.
	 *                               Note, that reverts throw exceptions.
	 */
	public function __construct(
		protected AbstractRPC                $rpc,
		protected Address                    $contractAddress,
		#[SensitiveParameter] protected ?Key $key = null,
		protected ?Address                   $fallbackFrom = null
	)
	{}

	/**
	 * Gets the contract address this object is bound to.
	 *
	 * @return Address Address of the contract.
	 */
	public function getContractAddress(): Address
	{ return $this->contractAddress; }

	/**
	 * Removes the private key from the object, making it no longer sign transactions, and fallback address will
	 * be used for estimations, if set, otherwise NULL address will be used.
	 *
	 * @return static The same object instance.
	 */
	public function unloadPrivK(): static
	{
		$this->key = null;
		return $this;
	}

	/**
	 * Loads a private key into the object for signing transactions. Calls will be done using address that comes from
	 * this key, and fallback address will be not used.
	 *
	 * @param Key $key The private key.
	 * @return static The same object instance.
	 */
	public function loadPrivK(#[SensitiveParameter] Key $key): static
	{
		$this->key = $key;
		return $this;
	}

	/**
	 * Sets a fallback address. See constructor documentation for details.
	 *
	 * @param Address $addr The address to set.
	 * @return static The same object instance.
	 */
	public function setFallbackFrom(Address $addr): static
	{
		$this->fallbackFrom = $addr;
		return $this;
	}

	/**
	 * Sets the fallback address to null. See constructor documentation for details.
	 *
	 * @return static The same object instance.
	 */
	public function unsetFallbackFrom(): static
	{
		$this->fallbackFrom = null;
		return $this;
	}

	/**
	 * @throws EthBinderLogicException
	 */
	private function getFromAddress(): Address
	{
		return $this->key?->toAddress() ?? $this->fallbackFrom ?? Address::NULL();
	}


	/**
	 * Parses all logs from the receipt and returns them in array, in order of events in receipt. All possible
	 * transaction types are listed as class-strings in static::getEventsRegistry()
	 *
	 * @param Receipt $rcpt The transaction receipt.
	 * @return AbstractEvent[] Array of parsed events.
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @throws EthBinderArgumentException
	 */
	public static function getEventsFromReceipt(Receipt $rcpt): array {
		if(static::class == self::class)
			throw new EthBinderLogicException("getEventsFromReceipt was called on AbstractContract. It must"
			." be called from concrete binding, not from abstract binding.");
		$o = [];
		$registry = static::getEventsRegistry();
		foreach($rcpt->logs AS $log) {
			foreach($registry AS $eventKind) {
				/** @var class-string<AbstractEvent> $eventKind */
				$tmp = $eventKind::parseEventFromLog($log);
				if($tmp != null) {
					$o[] = $tmp;
					continue 2;
				}
			}
		}
		return $o;
	}

	/**
	 * Executes a non-payable smart contract deploy.
	 *
	 * @param string $constructorParamsSig ABI constructor signature.
	 * @param Key $pk Private key for signing.
	 * @param AbstractRPC $rpc RPC interface for Ethereum.
	 * @param array $params ABI encoded constructor params.
	 * @return Transaction The resulting transaction.
	 * @throws UnexpectedUnsignedException
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	protected static function runNonPayableDeploy(
		string                    $constructorParamsSig,
		#[SensitiveParameter] Key $pk,
		AbstractRPC               $rpc,
		array                     $params
	): Transaction
	{
		$tx = self::getDeployTransaction($constructorParamsSig, $pk, $rpc, $params, null);
		$rpc->ethSendRawTransaction($tx);
		return $tx;
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @throws UnexpectedUnsignedException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	protected static function runPayableDeploy(
		string                    $constructorParamsSig,
		#[SensitiveParameter] Key $pk,
		AbstractRPC               $rpc,
		OOGmp                     $value,
		array                     $params
	): Transaction
	{
		$tx = self::getDeployTransaction($constructorParamsSig, $pk, $rpc, $params, $value);
		$rpc->ethSendRawTransaction($tx);
		return $tx;
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	private static function getDeployTransaction(
		string                    $constructorParamsSig,
		#[SensitiveParameter] Key $pk,
		AbstractRPC               $rpc,
		array                     $params,
		?OOGmp                    $value
	): Transaction
	{
		$tx = $rpc->isLookingLikeLondon() ?
			new LondonTransaction()
			: new LegacyTransaction();
		return $tx->setTo(null)
			->setValue(new OOGmp(0))
			->setDataBin(hex2bin(static::bytecode()).ABIEncoder::encode($constructorParamsSig, $params, false))
			->setNonce($rpc->ethGetTransactionCount($pk->toAddress())->toInt())
			->setValue($value ?? new OOGmp(0))
			->useRpcEstimatesWithBump(
				$rpc,
				$pk->toAddress(),
				self::$transactionFeesPercentageBump,
				self::$transactionFeesPercentageBump
			)->sign($pk, $rpc->ethChainID());
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws EthBinderArgumentException
	 */
	protected function parseOutput(string $output, string $type, ?array $tupleReplacements = null): mixed
	{
		if(str_starts_with($output, "0x"))
			$output = substr($output, 2);
		$output = hex2bin($output);
		$ret = ABIEncoder::decode($type, $output)->unwrapToPhpFriendlyVals($tupleReplacements);
		if(!is_array($ret))
			throw new EthBinderLogicException("got parse output without top level tuple");

		if(count($ret) == 1)
			return $ret[0];
		return $ret;
	}

	/**
	 * @throws EthBinderArgumentException
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 */
	protected function mkCall(string $signature, array $params = []): string
	{
		$tx = $this->_mkTxn($signature, $params, false);
		return $this->rpc->ethCall($tx, $this->getFromAddress());
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @throws UnexpectedUnsignedException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	protected function mkTxn(string $signature, array $params = []): Transaction
	{
		$txn = $this->_mkTxn($signature, $params, true);
		if($this->key === null)
			return $txn;
		$txn->sign($this->key, $this->rpc->ethChainID());
		if(!$this->noSend)
			$this->rpc->ethSendRawTransaction($txn);
		return $txn;
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws EthBinderArgumentException
	 * @throws RPCInvalidResponseParamException
	 */
	private function _mkTxn(string $signature, array $params, bool $careAboutEstims, bool $trimmedSignature = false): Transaction
	{
		$tx = $this->rpc->isLookingLikeLondon() ?
			new LondonTransaction()
			: new LegacyTransaction();
		$tx->setTo($this->contractAddress)
			->setValue(new OOGmp(0))
			->setDataBin(ABIEncoder::encode($signature, $params, !$trimmedSignature));
		return !$careAboutEstims ? $tx : $tx->setNonce(
			$this->rpc->ethGetTransactionCount($this->getFromAddress())
				->toInt()
		)->useRpcEstimatesWithBump(
			$this->rpc,
			$this->getFromAddress(),
			self::$transactionFeesPercentageBump,
			self::$transactionFeesPercentageBump
		);
	}

	/**
	 * @throws InvalidLengthException
	 * @throws EthBinderArgumentException
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 */
	protected function mkPayableTxn(string $signature, OOGmp $value, array $params): Transaction
	{
		$txn = $this->_mkTxn($signature, $params, true);
		$txn->setValue($value);
		if($this->key === null)
			return $txn;
		$txn->sign($this->key, $this->rpc->ethChainID());
		if(!$this->noSend)
			$this->rpc->ethSendRawTransaction($txn);
		return $txn;
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	protected function expectBinarySizeNormalizeString(string $binOrHex, int $length): string
	{
		if($length == 0 && str_starts_with($binOrHex, "0x") && ctype_xdigit(substr($binOrHex, 2)))
			return hex2bin(substr($binOrHex, 2));
		if(strlen($binOrHex) == $length || $length == 0)
			return $binOrHex;
		// if it does not start with 0x, but is valid hex, and length is 2* bin length, accept and cast to bin
		if(!str_starts_with($binOrHex, "0x") && ctype_xdigit($binOrHex) && strlen($binOrHex) == 2*$length)
			return hex2bin($binOrHex);
		// if starts with 0x and same as before
		if(str_starts_with($binOrHex, "0x") && ctype_xdigit(substr($binOrHex, 2)) && strlen($binOrHex) == 2+2*$length)
			return hex2bin(substr($binOrHex, 2));
		throw new EthBinderArgumentException("parameter isn't valid bytes$length");
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	protected function expectIntOfSize(bool $unsigned, int|OOGmp $value, int $bits): OOGmp
	{
		if(is_int($value))
			$value = new OOGmp($value);

		if($unsigned && $value->lt(0))
			throw new EthBinderArgumentException("parameter value is expected to be unsigned, but got value lower than 0");
		$actualBits = strlen(gmp_strval($value->raw(), 2));
		if($actualBits > $bits) {
			throw new EthBinderArgumentException("parameter value exceeded allowed amount of bits, provided value"
				." requires at least ".($unsigned ? "uint " : "int").$actualBits
				." but underlying is ".($unsigned ? "uint " : "int").$bits);
		}
		return $value;
	}

	/**
	 * @throws EthBinderArgumentException
	 */
	protected function expectIntArrOfSize(bool $unsigned, array $value, int $bits): array
	{
		$o = [];
		foreach($value AS $itm) {
			if(!($itm instanceof OOGmp)) {
				$itm = new OOGmp($itm);
			}

			if($unsigned && $itm->lt(0))
				throw new EthBinderArgumentException("value is lower than 0, cannot be unsigned int");
			if(strlen(gmp_strval($itm->raw(),2)) > $bits - ($unsigned?0:1)) {
				throw new EthBinderArgumentException("value is too big for size of the variable");
			}
			$o[] = $itm;
		}
		return $o;
	}

	/**
	 * Returns raw ABI JSON string, as is during abigen run.
	 * @return string
	 */
	abstract public static function abi(): string;

	/**
	 * Returns raw bytecode hex string, as is during abigen run. Used for deployment.
	 * Since bytecode is optional, the return is nullable, and when it is null, the deploy methods aren't generated.
	 * @return ?string
	 */
	abstract public static function bytecode(): ?string;
	abstract protected static function getEventsRegistry() : array;
}
