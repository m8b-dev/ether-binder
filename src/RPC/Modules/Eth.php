<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC\Modules;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Block;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Common\Receipt;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Exceptions\BadAddressChecksumException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\EthBinderRuntimeException;
use M8B\EtherBinder\Exceptions\HexBlobNotEvenException;
use M8B\EtherBinder\Exceptions\InvalidHexException;
use M8B\EtherBinder\Exceptions\InvalidHexLengthException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;
use M8B\EtherBinder\Exceptions\UnexpectedUnsignedException;
use M8B\EtherBinder\RPC\BlockParam;
use M8B\EtherBinder\Utils\Functions;
use M8B\EtherBinder\Utils\OOGmp;

abstract class Eth extends Debug
{
	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethProtocolVersion(): int
	{
		return $this->runRpc("eth_protocolVersion")[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethSyncing(): false|array
	{
		$d = $this->runRpc("eth_syncing");
		if(isset($d[0]) && $d[0] === false) {
			return false;
		}

		$return = [];
		$keys = ["startingBlock", "currentBlock", "highestBlock", "pulledStates", "knownStates"];
		foreach($keys AS $key) {
			$return[$key] = new OOGmp($d[$key] ?? 0, 16);
		}
		return $return;
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 * @throws EthBinderLogicException
	 */
	public function ethCoinbase(): Address
	{
		try {
			return Address::fromHex($this->runRpc("eth_coinbase")[0]);
		} catch(BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	private ?int $cachedChainId = null;

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethChainID(): int
	{
		if($this->cachedChainId === null)
			$this->cachedChainId = (int)hexdec($this->runRpc("eth_chainId")[0]);
		return $this->cachedChainId;
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function ethMining(): bool
	{
		return $this->runRpc("eth_mining")[0];
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function ethHashrate(): OOGmp
	{
		return new OOGmp($this->runRpc("eth_hashrate")[0]);
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGasPrice(): OOGmp
	{
		return new OOGmp($this->runRpc("eth_gasPrice")[0]);
	}

	/**
	 * @return Address[]
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethAccounts(): array
	{
		$accountsRaw = $this->runRpc("eth_accounts");
		$return = [];
		foreach($accountsRaw AS $accountRaw) {
			try {
				$return[] = Address::fromHex($accountRaw);
			} catch(BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
				throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
			}
		}
		return $return;
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethBlockNumber(): int
	{
		return (new OOGmp($this->runRpc("eth_blockNumber")[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetBalance(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getBalance",
			[$address->toHex(true), $this->blockParam($blockParam)]
		)[0]);
	}

	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethGetStorageAt(Address $address, OOGmp $position, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getStorageAt",
			[$address->toHex(true), $position->toString(true), $this->blockParam($blockParam)]
		)[0]);
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetTransactionCount(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_getTransactionCount", [$address->toHex(true), $this->blockParam($blockParam)]
		)[0]);
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetBlockTransactionCountByHash(Hash|Block $block): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getBlockTransactionCountByHash", [$this->blockHash($block)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetBlockTransactionCountByNumber(int|BlockParam $blockParam = BlockParam::LATEST): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getBlockTransactionCountByNumber", [$this->blockParam($blockParam)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetUncleCountByBlockHash(Hash|Block $block): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getUncleCountByBlockHash", [$this->blockHash($block)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderRuntimeException
	 */
	public function ethGetUncleCountByBlockNumber(int|BlockParam $blockParam = BlockParam::LATEST): int
	{
		return (new OOGmp($this->runRpc(
			"eth_getUncleCountByBlockNumber", [$this->blockParam($blockParam)]
		)[0]))->toInt();
	}

	/**
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 */
	public function ethGetCode(Address $address, int|BlockParam $blockParam = BlockParam::LATEST): string
	{
		return $this->runRpc(
			"eth_getCode", [$address->toHex(), $this->blockParam($blockParam)]
		)[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethSign(Address $address, string $dataHex): string
	{
		return $this->runRpc(
			"eth_sign", [$address->toHex(), $dataHex]
		)[0];
	}

	/**
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethSignTransaction(Transaction $txn, Address $from): string
	{
		return $this->runRpc("eth_signTransaction", [$this->transactionToRPCArr($txn, $from)])[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethSendTransaction(Transaction $txn, Address $from): Hash
	{
		try {
			return Hash::fromHex($this->runRpc("eth_sendTransaction", [$this->transactionToRPCArr($txn, $from)])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 * @throws UnexpectedUnsignedException
	 */
	public function ethSendRawTransaction(Transaction $signedTransaction): Hash
	{
		if(!$signedTransaction->isSigned())
			throw new UnexpectedUnsignedException();
		try {
			return Hash::fromHex($this->runRpc("eth_sendRawTransaction", [$signedTransaction->encodeHex()])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @param string $rawTransactionHex
	 * @return Hash
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethSendRawTransactionHex(string $rawTransactionHex): Hash
	{
		if(!str_starts_with($rawTransactionHex, "0x"))
			$rawTransactionHex = "0x".$rawTransactionHex;
		try {
			return Hash::fromHex($this->runRpc("eth_sendRawTransaction", [$rawTransactionHex])[0]);
		} catch(InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethCall(Transaction $message, ?Address $from = null, int|BlockParam $blockParam = BlockParam::LATEST): string
	{
		return $this->runRpc("eth_call", [$this->transactionToRPCArr($message, $from, true), $this->blockParam($blockParam)])[0];
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethEstimateGas(Transaction $txn, ?Address $from): int
	{
		return hexdec($this->runRpc("eth_estimateGas", [$this->transactionToRPCArr($txn, $from, true)])[0]);
	}

	/**
	 * @param Hash $hash
	 * @param bool $fullBlock
	 * @return Block
	 * @throws EthBinderLogicException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 */
	public function ethGetBlockByHash(Hash $hash, bool $fullBlock = false): Block
	{
		try {
			return Block::fromRPCArr($this->runRpc("eth_getBlockByHash", [$hash->toHex(), $fullBlock]));
		} catch(BadAddressChecksumException|HexBlobNotEvenException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetBlockByNumber(int|BlockParam $blockParam = BlockParam::LATEST, bool $fullBlock = false): Block
	{
		try {
			return Block::fromRPCArr($this->runRpc("eth_getBlockByNumber", [$this->blockParam($blockParam), $fullBlock]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws EthBinderLogicException
	 * @throws NotSupportedException
	 * @throws RPCGeneralException
	 * @throws RPCInvalidResponseParamException
	 * @throws RPCNotFoundException
	 */
	public function ethGetTransactionByHash(Hash $hash): Transaction
	{
		try {
			return Transaction::fromRPCArr($this->runRpc("eth_getTransactionByHash", [$hash->toHex()]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws NotSupportedException
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetTransactionByBlockHashAndIndex(Hash $hash, int $index): Transaction
	{
		try {
			return Transaction::fromRPCArr($this->runRpc(
				"eth_getTransactionByBlockHashAndIndex", [$hash->toHex(), "0x".dechex($index)]
			));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws NotSupportedException
	 * @throws EthBinderLogicException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethGetTransactionByBlockNumberAndIndex(int|BlockParam $blockParam, int $index): Transaction
	{
		try {
		return Transaction::fromRPCArr($this->runRpc("eth_getTransactionByBlockNumberAndIndex",
			[$this->blockParam($blockParam), "0x".dechex($index)]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws NotSupportedException
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetTransactionReceipt(Hash $hash): Receipt
	{
		try {
			return Receipt::fromRPCArr($this->runRpc("eth_getTransactionReceipt", [$hash->toHex()]));
		} catch(BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetUncleByBlockHashAndIndex(Hash $hash, int $unclePos): Block
	{
		try {
			return Block::fromRPCArr($this->runRpc("eth_getUncleByBlockHashAndIndex", [$hash->toHex(), "0x".dechex($unclePos)]));
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 * @throws EthBinderLogicException
	 */
	public function ethGetUncleByBlockNumberAndIndex(int|BlockParam $blockParam, int $unclePos):Block
	{
		try {
			return Block::fromRPCArr(
				$this->runRpc("eth_getUncleByBlockNumberAndIndex", [$this->blockParam($blockParam), "0x".dechex($unclePos)])
			);
		} catch(HexBlobNotEvenException|BadAddressChecksumException|InvalidHexLengthException|InvalidHexException $e) {
			throw new RPCInvalidResponseParamException("invalid data received: ".$e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws RPCGeneralException
	 * @throws RPCNotFoundException
	 * @throws RPCInvalidResponseParamException
	 */
	public function ethMaxPriorityFeePerGas(): OOGmp
	{
		return new OOGmp($this->runRpc(
			"eth_maxPriorityFeePerGas", []
		)[0]);
	}

	/* todo: add filters */

	private function transactionToRPCArr(Transaction $txn, ?Address $from, bool $asMessage = false): array
	{
		$txData = [];
		if($from === null)
			$from = Address::NULL();
		$txData["from"] = $from->toHex();

		if($txn->to() !== null) {
			$txData["to"] = $txn->to()->toHex();
		}
		if($txn->gasLimit() > 0)
			$txData["gas"] = Functions::int2hex($txn->gasLimit());

		$txData["gasPrice"] = $txn->totalGasPrice()->toString(true);
		$txData["value"] = $txn->value()->toString(true);
		$txData["data"] = $txn->dataHex();
		if($asMessage)
			return $txData;

		$txData["nonce"] = Functions::int2hex($txn->nonce());
		return $txData;
	}
}
