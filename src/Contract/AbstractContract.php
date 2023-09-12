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
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\OOGmp;

abstract class AbstractContract
{
	public static int $transactionFeesPercentageBump = 0;
	protected ?Key $key = null;
	protected ?Address $fallbackFrom = null;
	public function __construct(protected AbstractRPC $rpc, protected Address $contractAddress)
	{}

	public function unloadPrivK(): static
	{
		$this->key = null;
		return $this;
	}

	public function loadPrivK(#[\SensitiveParameter] Key $key): static
	{
		$this->key = $key;
		return $this;
	}

	public function setFallbackFrom(Address $addr): static
	{
		$this->fallbackFrom = $addr;
		return $this;
	}

	public function unsetFallbackFrom(): static
	{
		$this->fallbackFrom = null;
		return $this;
	}

	private function getFromAddress(): Address
	{
		return $this->key?->toAddress() ?? $this->fallbackFrom ?? Address::NULL();
	}

	protected static function runNonPayableDeploy(
		string $constructorParamsSig,
		#[\SensitiveParameter] Key $pk,
		AbstractRPC $rpc,
		array $params
	): Transaction
	{
		$tx = self::getDeployTransaction($constructorParamsSig, $pk, $rpc, $params, null);
		$rpc->ethSendRawTransaction($tx);
		return $tx;
	}

	protected static function runPayableDeploy(
		string $constructorParamsSig,
		#[\SensitiveParameter] Key $pk,
		AbstractRPC $rpc,
		OOGmp $value,
		array $params
	): Transaction
	{
		$tx = self::getDeployTransaction($constructorParamsSig, $pk, $rpc, $params, $value);
		$rpc->ethSendRawTransaction($tx);
		return $tx;
	}

	private static function getDeployTransaction(
		string $constructorParamsSig,
		#[\SensitiveParameter] Key $pk,
		AbstractRPC $rpc,
		array $params,
		?OOGmp $value
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

	protected function parseOutput(string $output, string $type): mixed
	{
		var_dump($type);
		var_dump($output);
		die;
	}

	public function mkCall(string $signature, array $params = []): string
	{
		$tx = $this->_mkTxn($signature, $params, false);
		return $this->rpc->ethCall($tx, $this->getFromAddress());
	}

	protected function mkTxn(string $signature, array $params = []): Transaction
	{
		$txn = $this->_mkTxn($signature, $params, true);
		if($this->key === null)
			return $txn;
		return $txn->sign($this->key, $this->rpc->ethChainID());
	}

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

	protected function mkPayableTxn(string $signature, OOGmp $value, array $params): Transaction
	{
		$txn = $this->_mkTxn($signature, $params, true);
		$txn->setValue($value);
		if($this->key === null)
			return $txn;
		return $txn->sign($this->key, $this->rpc->ethChainID());
	}

	protected function expectBinarySizeNormalizeString(string $binOrHex, int $length): string
	{
		if($length == 0 && str_starts_with($binOrHex, "0x") && ctype_xdigit(substr($binOrHex, 2)))
			return hex2bin(substr($binOrHex, 2));
		if(strlen($binOrHex) == $length || $length == 0)
			return $binOrHex;
		// if does not start with 0x, but is valid hex, and length is 2* bin length, accept and cast to bin
		if(!str_starts_with($binOrHex, "0x") && ctype_xdigit($binOrHex) && strlen($binOrHex) == 2*$length)
			return hex2bin($binOrHex);
		// if starts with 0x and same as before
		if(str_starts_with($binOrHex, "0x") && ctype_xdigit(substr($binOrHex, 2)) && strlen($binOrHex) == 2+2*$length)
			return hex2bin(substr($binOrHex, 2));
		throw new \InvalidArgumentException("parameter isn't valid bytes$length");
	}

	protected function expectIntOfSize(bool $unsigned, int|OOGmp $value, int $bits): OOGmp
	{
		if(is_int($value))
			$value = new OOGmp($value);

		if($unsigned && $value->lt(0))
			throw new \InvalidArgumentException("parameter value is expected to be unsigned, but got value lower than 0");
		$actualBits = strlen(gmp_strval($value->raw(), 2));
		if($actualBits > $bits) {
			throw new \InvalidArgumentException("parameter value exceeded allowed amount of bits, provided value"
				." requires at least ".($unsigned ? "uint " : "int").$actualBits
				." but underlying is ".($unsigned ? "uint " : "int").$bits);
		}
		return $value;
	}

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

	abstract public static function abi(): string;
	abstract public static function bytecode(): ?string;
	abstract protected static function getEventsRegistry() : array;
}
